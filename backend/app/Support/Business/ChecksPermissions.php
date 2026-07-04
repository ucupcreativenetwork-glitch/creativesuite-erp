<?php

namespace App\Support\Business;

use App\Modules\Core\Models\User;
use App\Support\Exceptions\ApiException;

trait ChecksPermissions
{
    protected function assertPermission(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }
    }

    protected function assertSameTenant(User $user, int $tenantId): void
    {
        if ($user->tenant_id !== $tenantId) {
            throw new ApiException('Forbidden.', 403, 'TENANT_MISMATCH');
        }
    }
}