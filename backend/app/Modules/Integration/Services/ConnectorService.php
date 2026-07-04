<?php

namespace App\Modules\Integration\Services;

use App\Modules\Core\Models\User;
use App\Modules\Integration\Enums\ConnectorType;
use App\Modules\Integration\Enums\WebhookEvent;
use App\Modules\Integration\Models\ConnectorConfig;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Str;

class ConnectorService
{
    use ChecksPermissions;

    public function __construct(
        protected AttendanceImportService $attendanceImport,
        protected WebhookService $webhookService,
        protected IntegrationLogService $integrationLogService,
    ) {}

    public function list(User $user)
    {
        $this->assertPermission($user, 'int.connector.read');

        return ConnectorConfig::query()->orderByDesc('created_at')->get();
    }

    public function create(User $user, array $data): array
    {
        $this->assertPermission($user, 'int.connector.manage');

        $type = ConnectorType::from($data['connector_type']);
        $ingestToken = Str::random(48);

        $connector = ConnectorConfig::query()->create([
            'public_id' => (string) Str::uuid(),
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'connector_type' => $type,
            'name' => $data['name'],
            'ingest_token' => hash('sha256', $ingestToken),
            'employee_match_field' => $data['employee_match_field'] ?? 'employee_number',
            'settings' => $data['settings'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
        ]);

        return [
            'connector' => $connector,
            'ingest_token' => $ingestToken,
            'push_url' => url("/api/v1/external/connectors/push"),
        ];
    }

    public function update(User $user, string $publicId, array $data): ConnectorConfig
    {
        $this->assertPermission($user, 'int.connector.manage');

        $connector = $this->findScoped($user, $publicId);
        $connector->update(array_filter([
            'name' => $data['name'] ?? null,
            'employee_match_field' => $data['employee_match_field'] ?? null,
            'settings' => $data['settings'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn ($v) => $v !== null));

        return $connector->fresh();
    }

    public function destroy(User $user, string $publicId): void
    {
        $this->assertPermission($user, 'int.connector.manage');
        $this->findScoped($user, $publicId)->delete();
    }

    public function ingestPush(string $ingestToken, array $payload): array
    {
        $hash = hash('sha256', $ingestToken);

        $connector = ConnectorConfig::query()
            ->withoutGlobalScopes()
            ->where('ingest_token', $hash)
            ->where('is_active', true)
            ->first();

        if (! $connector) {
            throw new ApiException('Invalid connector token.', 401, 'INVALID_CONNECTOR_TOKEN');
        }

        $records = $this->normalizePayload($connector, $payload);
        $processed = 0;
        $errors = [];

        foreach ($records as $index => $record) {
            try {
                $employeeRef = $this->buildEmployeeReference($connector->employee_match_field, $record);

                $type = strtolower($record['type'] ?? $record['punch_type'] ?? 'in');
                if (in_array($type, ['out', 'checkout', 'clock_out'], true)) {
                    $this->attendanceImport->clockByEmployee(
                        $connector->tenant_id,
                        $connector->company_id,
                        'out',
                        array_merge($employeeRef, [
                            'timestamp' => $record['timestamp'] ?? $record['time'] ?? now()->toIso8601String(),
                            'external_ref' => $record['external_ref'] ?? null,
                        ]),
                        $connector->connector_type->value,
                    );
                } else {
                    $this->attendanceImport->clockByEmployee(
                        $connector->tenant_id,
                        $connector->company_id,
                        'in',
                        array_merge($employeeRef, [
                            'timestamp' => $record['timestamp'] ?? $record['time'] ?? now()->toIso8601String(),
                            'external_ref' => $record['external_ref'] ?? null,
                        ]),
                        $connector->connector_type->value,
                    );
                }
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = ['index' => $index, 'message' => $e->getMessage()];
            }
        }

        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $this->integrationLogService->persistIngestLog(
            $connector,
            $processed,
            $errors,
            $payloadHash,
        );

        $this->webhookService->dispatch(
            $connector->tenant_id,
            $connector->company_id,
            WebhookEvent::ConnectorReceived,
            [
                'connector_public_id' => $connector->public_id,
                'connector_type' => $connector->connector_type->value,
                'processed' => $processed,
                'errors' => $errors,
            ],
        );

        return ['processed' => $processed, 'errors' => $errors];
    }

    protected function normalizePayload(ConnectorConfig $connector, array $payload): array
    {
        if (! empty($payload['records']) && is_array($payload['records'])) {
            return $payload['records'];
        }

        if ($connector->connector_type === ConnectorType::Zkteco && ! empty($payload['PIN'])) {
            return [[
                'pin' => $payload['PIN'],
                'timestamp' => $payload['DateTime'] ?? now()->toIso8601String(),
                'type' => ($payload['Status'] ?? 0) == 1 ? 'out' : 'in',
            ]];
        }

        if ($connector->connector_type === ConnectorType::Hikvision) {
            return $this->normalizeHikvisionPayload($payload);
        }

        if (isset($payload['employee_number']) || isset($payload['pin'])) {
            return [$payload];
        }

        throw new ApiException('Unrecognized connector payload format.', 422, 'INVALID_PAYLOAD');
    }

    protected function normalizeHikvisionPayload(array $payload): array
    {
        $event = $payload['AccessControllerEvent']
            ?? $payload['accessControllerEvent']
            ?? $payload['event']
            ?? $payload;

        if (isset($event[0]) && is_array($event[0])) {
            return array_map(fn (array $row) => $this->mapHikvisionEvent($row), $event);
        }

        if (is_array($event)) {
            return [$this->mapHikvisionEvent($event)];
        }

        throw new ApiException('Unrecognized Hikvision payload.', 422, 'INVALID_HIKVISION_PAYLOAD');
    }

    protected function mapHikvisionEvent(array $event): array
    {
        $employeeNo = $event['employeeNoString']
            ?? $event['employeeNo']
            ?? $event['cardNo']
            ?? null;

        $dateTime = $event['dateTime'] ?? $event['time'] ?? now()->toIso8601String();

        $status = strtolower((string) ($event['attendanceStatus'] ?? $event['label'] ?? $event['direction'] ?? 'checkin'));
        $type = in_array($status, ['checkout', 'check out', 'out', 'leave', '2'], true) ? 'out' : 'in';

        return [
            'employee_number' => (string) $employeeNo,
            'pin' => (string) $employeeNo,
            'timestamp' => $dateTime,
            'type' => $type,
            'external_ref' => $event['serialNo'] ?? $event['serialno'] ?? null,
        ];
    }

    protected function buildEmployeeReference(string $matchField, array $record): array
    {
        $value = match ($matchField) {
            'device_pin' => $record['device_pin'] ?? $record['pin'] ?? $record['PIN'] ?? $record['employeeNoString'] ?? $record['employee_number'] ?? null,
            'employee_number' => $record['employee_number'] ?? $record['employeeNoString'] ?? $record['pin'] ?? $record['PIN'] ?? null,
            'pin' => $record['pin'] ?? $record['PIN'] ?? $record['device_pin'] ?? $record['employee_number'] ?? null,
            default => $record[$matchField] ?? $record['pin'] ?? null,
        };

        if ($value === null || $value === '') {
            throw new ApiException('Employee reference missing in connector record.', 422, 'EMPLOYEE_REF_REQUIRED');
        }

        $key = $matchField === 'pin' ? 'pin' : $matchField;

        return [$key => (string) $value];
    }

    protected function findScoped(User $user, string $publicId): ConnectorConfig
    {
        return ConnectorConfig::query()
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}