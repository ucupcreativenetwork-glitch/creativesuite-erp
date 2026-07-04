<?php

namespace App\Modules\Core\Repositories;

use App\Modules\Core\Models\Role;
use App\Modules\Core\Repositories\Contracts\RoleRepositoryInterface;
use App\Support\Database\BaseRepository;

class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    protected function model(): string
    {
        return Role::class;
    }

    public function findByCode(int $tenantId, string $code): ?Role
    {
        return Role::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();
    }

    public function create(array $data): Role
    {
        return Role::query()->create($data);
    }
}