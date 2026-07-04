<?php

namespace App\Modules\Core\Repositories\Contracts;

use App\Modules\Core\Models\Role;

interface RoleRepositoryInterface
{
    public function findByCode(int $tenantId, string $code): ?Role;

    public function create(array $data): Role;
}