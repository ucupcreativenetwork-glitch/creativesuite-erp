<?php

namespace App\Modules\Iam\Services;

use App\Modules\Core\Models\User;
use App\Modules\Iam\Models\AuditLog;

class AuditLogService
{
    public function record(
        User $actor,
        string $eventType,
        string $entityType,
        int $entityId,
        ?string $entityPublicId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $companyId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'tenant_id' => $actor->tenant_id,
            'company_id' => $companyId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_public_id' => $entityPublicId,
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'request_id' => request()->header('X-Request-ID'),
            ],
        ]);
    }
}