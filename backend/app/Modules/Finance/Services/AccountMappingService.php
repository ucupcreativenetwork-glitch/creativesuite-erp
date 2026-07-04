<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\AccountMappingKey;
use App\Modules\Finance\Models\AccountMapping;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Support\Exceptions\ApiException;

class AccountMappingService
{
    public function getAccountId(int $companyId, AccountMappingKey $key): int
    {
        $mapping = AccountMapping::query()
            ->where('company_id', $companyId)
            ->where('mapping_key', $key->value)
            ->first();

        if (! $mapping) {
            throw new ApiException(
                "Account mapping '{$key->value}' not configured.",
                422,
                'ACCOUNT_MAPPING_MISSING',
            );
        }

        return $mapping->account_id;
    }

    public function getAccount(int $companyId, AccountMappingKey $key): ChartOfAccount
    {
        return ChartOfAccount::query()->findOrFail($this->getAccountId($companyId, $key));
    }

    public function listForCompany(int $companyId): array
    {
        return AccountMapping::query()
            ->where('company_id', $companyId)
            ->with('account')
            ->get()
            ->map(fn ($m) => [
                'mapping_key' => $m->mapping_key,
                'account_id' => $m->account_id,
                'account_code' => $m->account?->code,
                'account_name' => $m->account?->name,
            ])
            ->all();
    }
}