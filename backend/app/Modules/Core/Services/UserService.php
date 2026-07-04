<?php

namespace App\Modules\Core\Services;

use App\Modules\Auth\Enums\UserAccountStatus;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Iam\Services\AuditLogService;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

class UserService
{
    use ChecksPermissions;

    public function __construct(protected AuditLogService $auditLog) {}

    public function list(User $actor, array $filters = [])
    {
        $this->assertPermission($actor, 'core.user.read');

        $query = User::query()
            ->with(['roles', 'defaultCompany'])
            ->where('tenant_id', $actor->tenant_id)
            ->orderBy('full_name');

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function show(User $actor, string $publicId): User
    {
        $this->assertPermission($actor, 'core.user.read');

        return User::query()
            ->where('public_id', $publicId)
            ->where('tenant_id', $actor->tenant_id)
            ->with(['roles.permissions', 'defaultCompany', 'defaultBranch', 'companies'])
            ->firstOrFail();
    }

    public function update(User $actor, string $publicId, array $data): User
    {
        $this->assertPermission($actor, 'core.user.update');

        $user = User::query()
            ->where('public_id', $publicId)
            ->where('tenant_id', $actor->tenant_id)
            ->firstOrFail();

        if (isset($data['is_active']) && $data['is_active'] === false && $user->id === $actor->id) {
            throw new ApiException('You cannot deactivate your own account.', 422, 'CANNOT_DEACTIVATE_SELF');
        }

        $user->update(array_filter($data, fn ($v) => $v !== null));

        return $user->fresh(['roles', 'defaultCompany', 'defaultBranch']);
    }

    public function assignRoles(User $actor, string $publicId, array $roleIds): User
    {
        $this->assertPermission($actor, 'core.user.update');

        $user = User::query()
            ->where('public_id', $publicId)
            ->where('tenant_id', $actor->tenant_id)
            ->firstOrFail();

        $validRoleIds = Role::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereIn('id', $roleIds)
            ->pluck('id')
            ->all();

        if (count($validRoleIds) !== count(array_unique($roleIds))) {
            throw new ApiException('One or more roles are invalid for this tenant.', 422, 'INVALID_ROLES');
        }

        return DB::transaction(function () use ($user, $validRoleIds, $actor) {
            $syncData = [];

            foreach ($validRoleIds as $roleId) {
                $syncData[$roleId] = [
                    'tenant_id' => $actor->tenant_id,
                    'branch_id' => null,
                ];
            }

            $user->roles()->sync($syncData);

            return $user->fresh(['roles.permissions', 'defaultCompany']);
        });
    }

    public function disable(User $actor, string $publicId): User
    {
        $user = User::query()
            ->where('public_id', $publicId)
            ->where('tenant_id', $actor->tenant_id)
            ->with('roles')
            ->firstOrFail();

        if ($user->id === $actor->id) {
            throw new ApiException('Tidak dapat menghapus akun sendiri.', 422, 'CANNOT_DELETE_SELF');
        }

        if ($user->roles->contains('code', 'TENANT_OWNER')) {
            throw new ApiException('Tidak dapat menghapus akun Tenant Owner.', 422, 'CANNOT_DELETE_OWNER');
        }

        if ($user->account_status === UserAccountStatus::Disabled->value) {
            throw new ApiException('User sudah dihapus.', 422, 'ALREADY_DISABLED');
        }

        return DB::transaction(function () use ($actor, $user) {
            $previousStatus = $user->account_status;

            $user->roles()->detach();
            $user->companies()->detach();
            $user->update([
                'is_active' => false,
                'account_status' => UserAccountStatus::Disabled->value,
                'must_change_password' => true,
            ]);

            $this->auditLog->record(
                $actor,
                'USER_DISABLED',
                'User',
                $user->id,
                $user->public_id,
                ['is_active' => true, 'account_status' => $previousStatus],
                ['is_active' => false, 'account_status' => UserAccountStatus::Disabled->value],
                $actor->default_company_id,
            );

            return $user->fresh(['roles']);
        });
    }
}