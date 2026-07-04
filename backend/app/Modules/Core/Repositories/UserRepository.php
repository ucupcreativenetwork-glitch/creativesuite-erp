<?php

namespace App\Modules\Core\Repositories;

use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyAccess;
use App\Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Database\BaseRepository;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function model(): string
    {
        return User::class;
    }

    public function findByEmail(int $tenantId, string $email): ?User
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->first();
    }

    public function findByEmailGlobal(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function emailExistsInTenant(int $tenantId, string $email): bool
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->exists();
    }

    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function updateLastLogin(User $user, ?string $ip): void
    {
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    public function assignRole(User $user, int $roleId, ?int $branchId = null): void
    {
        $exists = $user->roles()
            ->where('cs_core_roles.id', $roleId)
            ->wherePivot('branch_id', $branchId)
            ->exists();

        if (! $exists) {
            $user->roles()->attach($roleId, [
                'tenant_id' => $user->tenant_id,
                'branch_id' => $branchId,
            ]);
        }
    }

    public function grantCompanyAccess(User $user, int $companyId, bool $isDefault = false): void
    {
        UserCompanyAccess::query()->updateOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'company_id' => $companyId,
            ],
            ['is_default' => $isDefault],
        );
    }
}