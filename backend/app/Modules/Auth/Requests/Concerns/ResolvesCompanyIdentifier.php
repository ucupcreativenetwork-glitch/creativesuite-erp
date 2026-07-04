<?php

namespace App\Modules\Auth\Requests\Concerns;

trait ResolvesCompanyIdentifier
{
    protected function companyIdentifierRules(): array
    {
        return [
            'company_name' => ['required_without:tenant_slug', 'nullable', 'string', 'max:200'],
            'tenant_slug' => ['required_without:company_name', 'nullable', 'string', 'max:100'],
        ];
    }

    public function companyIdentifier(): string
    {
        $companyName = trim((string) $this->input('company_name', ''));
        $tenantSlug = trim((string) $this->input('tenant_slug', ''));

        return $companyName !== '' ? $companyName : $tenantSlug;
    }
}