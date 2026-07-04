<?php

namespace App\Modules\Core\Repositories\Contracts;

use App\Modules\Core\Models\User;

interface UserRepositoryInterface
{
    public function findByEmail(int $tenantId, string $email): ?User;

    public function findByEmailGlobal(string $email): ?User;

    public function emailExistsInTenant(int $tenantId, string $email): bool;

    public function create(array $data): User;

    public function updateLastLogin(User $user, ?string $ip): void;

    public function assignRole(User $user, int $roleId, ?int $branchId = null): void;

    public function grantCompanyAccess(User $user, int $companyId, bool $isDefault = false): void;
}