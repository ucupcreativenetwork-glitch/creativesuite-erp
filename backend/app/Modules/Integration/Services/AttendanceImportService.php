<?php

namespace App\Modules\Integration\Services;

use App\Modules\Business\Enums\AttendanceStatus;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\Employee;
use App\Modules\Integration\Enums\WebhookEvent;
use App\Support\Exceptions\ApiException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttendanceImportService
{
    public function __construct(protected WebhookService $webhookService) {}

    public function importBulk(int $tenantId, int $companyId, array $records, ?string $source = 'api'): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($tenantId, $companyId, $records, $source, &$imported, &$skipped, &$errors): void {
            foreach ($records as $index => $row) {
                try {
                    $result = $this->upsertRecord($tenantId, $companyId, $row, $source);
                    if ($result === null) {
                        $skipped++;
                    } else {
                        $imported++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = ['index' => $index, 'message' => $e->getMessage()];
                }
            }
        });

        if ($imported > 0) {
            $this->webhookService->dispatch($tenantId, $companyId, WebhookEvent::AttendanceImported, [
                'imported' => $imported,
                'skipped' => $skipped,
                'source' => $source,
            ]);
        }

        return compact('imported', 'skipped', 'errors');
    }

    public function clockByEmployee(
        int $tenantId,
        int $companyId,
        string $type,
        array $data,
        ?string $source = 'api',
    ): AttendanceRecord {
        $employee = $this->resolveEmployee($tenantId, $companyId, $data);
        $today = isset($data['attendance_date'])
            ? Carbon::parse($data['attendance_date'])->toDateString()
            : now()->toDateString();
        $now = isset($data['timestamp']) ? Carbon::parse($data['timestamp']) : now();

        $record = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $today)
            ->first();

        if ($type === 'in') {
            if ($record?->clock_in_at) {
                throw new ApiException('Already clocked in.', 422, 'ALREADY_CLOCKED_IN');
            }

            $lateMinutes = $this->calculateLateMinutes($now);
            $payload = [
                'clock_in_at' => $now,
                'status' => ($data['status'] ?? null)
                    ? AttendanceStatus::from($data['status'])
                    : ($lateMinutes > 0 ? AttendanceStatus::Late : AttendanceStatus::Present),
                'late_minutes' => $lateMinutes,
                'source' => $source,
                'external_ref' => $data['external_ref'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            if ($record) {
                $record->update($payload);
            } else {
                $record = AttendanceRecord::query()->create([
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'public_id' => (string) Str::uuid(),
                    'employee_id' => $employee->id,
                    'attendance_date' => $today,
                    ...$payload,
                ]);
            }
        } else {
            if (! $record?->clock_in_at) {
                throw new ApiException('Not clocked in.', 422, 'NOT_CLOCKED_IN');
            }
            if ($record->clock_out_at) {
                throw new ApiException('Already clocked out.', 422, 'ALREADY_CLOCKED_OUT');
            }

            $workMinutes = max(0, $record->clock_in_at->diffInMinutes($now));
            $record->update([
                'clock_out_at' => $now,
                'work_minutes' => $workMinutes,
                'source' => $source,
                'external_ref' => $data['external_ref'] ?? $record->external_ref,
            ]);
        }

        $fresh = $record->fresh(['employee']);

        $this->webhookService->dispatch($tenantId, $companyId, WebhookEvent::AttendanceRecorded, [
            'public_id' => $fresh->public_id,
            'employee_number' => $fresh->employee?->employee_number,
            'attendance_date' => $fresh->attendance_date?->toDateString(),
            'clock_in_at' => $fresh->clock_in_at?->toIso8601String(),
            'clock_out_at' => $fresh->clock_out_at?->toIso8601String(),
            'status' => $fresh->status?->value,
            'source' => $fresh->source,
        ]);

        return $fresh;
    }

    protected function upsertRecord(int $tenantId, int $companyId, array $row, ?string $source): ?AttendanceRecord
    {
        $employee = $this->resolveEmployee($tenantId, $companyId, $row);
        $date = Carbon::parse($row['attendance_date'])->toDateString();

        $existing = AttendanceRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->first();

        if ($existing && ! empty($row['skip_if_exists'])) {
            return null;
        }

        $clockIn = ! empty($row['clock_in_at']) ? Carbon::parse($row['clock_in_at']) : null;
        $clockOut = ! empty($row['clock_out_at']) ? Carbon::parse($row['clock_out_at']) : null;
        $status = ! empty($row['status'])
            ? AttendanceStatus::from($row['status'])
            : ($clockIn ? AttendanceStatus::Present : AttendanceStatus::Absent);

        $workMinutes = ($clockIn && $clockOut) ? max(0, $clockIn->diffInMinutes($clockOut)) : 0;
        $lateMinutes = $clockIn ? $this->calculateLateMinutes($clockIn) : 0;

        $payload = [
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'status' => $status,
            'work_minutes' => $workMinutes,
            'late_minutes' => $lateMinutes,
            'source' => $row['source'] ?? $source,
            'external_ref' => $row['external_ref'] ?? null,
            'notes' => $row['notes'] ?? null,
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return AttendanceRecord::query()->create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'public_id' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            ...$payload,
        ]);
    }

    protected function resolveEmployee(int $tenantId, int $companyId, array $data): Employee
    {
        $query = Employee::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('status', EmployeeStatus::Active);

        if (! empty($data['employee_public_id'])) {
            $query->where('public_id', $data['employee_public_id']);
        } elseif (! empty($data['device_pin'])) {
            $query->where('device_pin', $data['device_pin']);
        } elseif (! empty($data['employee_number'])) {
            $query->where('employee_number', $data['employee_number']);
        } elseif (! empty($data['pin'])) {
            $employee = (clone $query)->where('device_pin', $data['pin'])->first();
            if ($employee) {
                return $employee;
            }

            $query->where('employee_number', $data['pin']);
        } else {
            throw new ApiException('employee_number, device_pin, or employee_public_id required.', 422, 'EMPLOYEE_REF_REQUIRED');
        }

        $employee = $query->first();
        if (! $employee) {
            throw new ApiException('Employee not found.', 404, 'EMPLOYEE_NOT_FOUND');
        }

        return $employee;
    }

    protected function calculateLateMinutes(Carbon $clockIn): int
    {
        $workStart = config('hr.work_start', '08:00');
        $grace = (int) config('hr.late_grace_minutes', 15);

        [$hour, $minute] = array_map('intval', explode(':', $workStart));
        $scheduled = $clockIn->copy()->setTime($hour, $minute, 0)->addMinutes($grace);

        if ($clockIn->lte($scheduled)) {
            return 0;
        }

        return (int) $scheduled->diffInMinutes($clockIn);
    }
}