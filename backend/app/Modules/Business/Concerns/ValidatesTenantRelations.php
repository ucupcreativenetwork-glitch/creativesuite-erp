<?php

namespace App\Modules\Business\Concerns;

use App\Modules\Business\Models\CrmAccount;
use App\Modules\Business\Models\InvItem;
use App\Modules\Business\Models\InvWarehouse;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Support\Exceptions\ApiException;

trait ValidatesTenantRelations
{
    protected function assertAccountInScope(User $user, ?int $accountId): void
    {
        if ($accountId === null) {
            return;
        }

        $exists = CrmAccount::query()
            ->where('id', $accountId)
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->exists();

        if (! $exists) {
            throw new ApiException('Account not found in current company.', 422, 'INVALID_ACCOUNT');
        }
    }

    protected function assertItemInScope(User $user, ?int $itemId): void
    {
        if ($itemId === null) {
            return;
        }

        $exists = InvItem::query()
            ->where('id', $itemId)
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->exists();

        if (! $exists) {
            throw new ApiException('Item not found in current company.', 422, 'INVALID_ITEM');
        }
    }

    protected function assertWarehouseInScope(User $user, ?int $warehouseId): void
    {
        if ($warehouseId === null) {
            return;
        }

        $exists = InvWarehouse::query()
            ->where('id', $warehouseId)
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->exists();

        if (! $exists) {
            throw new ApiException('Warehouse not found in current company.', 422, 'INVALID_WAREHOUSE');
        }
    }

    protected function assertUserInTenant(User $user, ?int $targetUserId): void
    {
        if ($targetUserId === null) {
            return;
        }

        $exists = User::query()
            ->where('id', $targetUserId)
            ->where('tenant_id', $user->tenant_id)
            ->exists();

        if (! $exists) {
            throw new ApiException('User not found in current tenant.', 422, 'INVALID_USER');
        }
    }

    protected function assertRolesInTenant(User $user, array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }

        $count = Role::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('id', $roleIds)
            ->count();

        if ($count !== count(array_unique($roleIds))) {
            throw new ApiException('One or more roles are invalid for this tenant.', 422, 'INVALID_ROLES');
        }
    }
}