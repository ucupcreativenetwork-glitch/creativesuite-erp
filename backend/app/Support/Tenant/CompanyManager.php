<?php

namespace App\Support\Tenant;

class CompanyManager
{
    protected ?int $companyId = null;

    public function set(int $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function clear(): void
    {
        $this->companyId = null;
    }
}