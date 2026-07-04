<?php

namespace App\Modules\Integration\Services;

use App\Modules\Core\Models\User;
use App\Modules\Integration\Models\ConnectorConfig;
use App\Modules\Integration\Models\ConnectorIngestLog;
use App\Support\Business\ChecksPermissions;

class IntegrationLogService
{
    use ChecksPermissions;

    public function listConnectorLogs(User $user, string $connectorPublicId, array $filters = [])
    {
        $this->assertPermission($user, 'int.connector.read');

        $connector = ConnectorConfig::query()
            ->where('public_id', $connectorPublicId)
            ->firstOrFail();

        return ConnectorIngestLog::query()
            ->where('connector_id', $connector->id)
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 25);
    }

    public function persistIngestLog(
        ConnectorConfig $connector,
        int $processed,
        array $errors,
        string $payloadHash,
    ): ConnectorIngestLog {
        $log = ConnectorIngestLog::query()->create([
            'connector_id' => $connector->id,
            'processed' => $processed,
            'errors' => $errors,
            'payload_hash' => $payloadHash,
            'created_at' => now(),
        ]);

        $connector->update([
            'last_ingest_at' => now(),
            'last_processed_count' => $processed,
            'last_error_count' => count($errors),
        ]);

        return $log;
    }
}