<?php

namespace App\Modules\Core\Repositories;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Repositories\Contracts\BranchRepositoryInterface;
use App\Support\Database\BaseRepository;

class BranchRepository extends BaseRepository implements BranchRepositoryInterface
{
    protected function model(): string
    {
        return Branch::class;
    }

    public function create(array $data): Branch
    {
        return Branch::query()->create($data);
    }
}