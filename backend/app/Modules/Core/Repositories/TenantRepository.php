<?php

namespace App\Modules\Core\Repositories;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Repositories\Contracts\TenantRepositoryInterface;
use App\Support\Database\BaseRepository;

class TenantRepository extends BaseRepository implements TenantRepositoryInterface
{
    protected function model(): string
    {
        return Tenant::class;
    }

    public function findBySlug(string $slug): ?Tenant
    {
        return Tenant::query()->where('slug', $slug)->first();
    }

    public function slugExists(string $slug): bool
    {
        return Tenant::query()->where('slug', $slug)->exists();
    }

    public function create(array $data): Tenant
    {
        return Tenant::query()->create($data);
    }
}