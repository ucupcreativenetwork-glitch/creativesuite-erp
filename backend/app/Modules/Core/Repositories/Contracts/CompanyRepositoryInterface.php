<?php

namespace App\Modules\Core\Repositories\Contracts;

use App\Modules\Core\Models\Company;

interface CompanyRepositoryInterface
{
    public function create(array $data): Company;
}