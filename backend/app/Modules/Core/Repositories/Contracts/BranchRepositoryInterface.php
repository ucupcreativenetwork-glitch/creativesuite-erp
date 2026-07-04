<?php

namespace App\Modules\Core\Repositories\Contracts;

use App\Modules\Core\Models\Branch;

interface BranchRepositoryInterface
{
    public function create(array $data): Branch;
}