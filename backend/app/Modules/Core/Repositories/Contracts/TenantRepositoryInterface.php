<?php

namespace App\Modules\Core\Repositories\Contracts;

use App\Modules\Core\Models\Tenant;

interface TenantRepositoryInterface
{
    public function findBySlug(string $slug): ?Tenant;

    public function slugExists(string $slug): bool;

    public function create(array $data): Tenant;
}