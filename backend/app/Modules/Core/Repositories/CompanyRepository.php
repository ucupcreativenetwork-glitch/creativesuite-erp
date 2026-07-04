<?php

namespace App\Modules\Core\Repositories;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Repositories\Contracts\CompanyRepositoryInterface;
use App\Support\Database\BaseRepository;

class CompanyRepository extends BaseRepository implements CompanyRepositoryInterface
{
    protected function model(): string
    {
        return Company::class;
    }

    public function create(array $data): Company
    {
        return Company::query()->create($data);
    }
}