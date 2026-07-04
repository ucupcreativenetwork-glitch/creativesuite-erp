<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Data\DefaultCoaTemplate;
use App\Modules\Finance\Models\AccountMapping;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FiscalPeriod;
use App\Modules\Finance\Enums\FiscalPeriodStatus;
use Illuminate\Support\Str;

class CoaSetupService
{
    public function setupForCompany(int $tenantId, int $companyId): void
    {
        if (ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->exists()) {
            return;
        }

        $codeMap = [];

        foreach (DefaultCoaTemplate::accounts() as $item) {
            $parentId = isset($item['parent_code']) && $item['parent_code']
                ? ($codeMap[$item['parent_code']] ?? null)
                : null;

            $account = ChartOfAccount::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'public_id' => (string) Str::uuid(),
                'code' => $item['code'],
                'name' => $item['name'],
                'category' => $item['category'],
                'account_type' => $item['account_type'],
                'parent_id' => $parentId,
                'normal_balance' => $item['normal_balance'],
                'is_postable' => $item['is_postable'],
                'is_active' => true,
            ]);

            $codeMap[$item['code']] = $account->id;

            if (! empty($item['mapping'])) {
                AccountMapping::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'company_id' => $companyId,
                        'mapping_key' => $item['mapping']->value,
                    ],
                    ['account_id' => $account->id],
                );
            }
        }

        $this->ensureFiscalPeriods($tenantId, $companyId);
    }

    public function ensureFiscalPeriods(int $tenantId, int $companyId, ?int $year = null): void
    {
        $year = $year ?? (int) now()->year;

        for ($month = 1; $month <= 12; $month++) {
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = date('Y-m-t', strtotime($start));

            FiscalPeriod::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'name' => sprintf('%04d-%02d', $year, $month),
                    'start_date' => $start,
                    'end_date' => $end,
                    'status' => FiscalPeriodStatus::Open,
                ],
            );
        }
    }
}