<?php

namespace App\Modules\Finance\Services;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\PpnTransactionType;
use App\Modules\Finance\Models\PpnTransaction;
use App\Modules\Finance\Models\SptMasaPpn;
use App\Support\Exceptions\ApiException;

class SptMasaPpnService
{
    public function generate(User $user, int $year, int $month): SptMasaPpn
    {
        $this->assertPermission($user, 'fin.tax.spt.create');

        $transactions = PpnTransaction::query()
            ->where('fiscal_year', $year)
            ->where('fiscal_month', $month)
            ->get();

        $totalPk = $transactions
            ->where('transaction_type', PpnTransactionType::Output)
            ->sum('ppn_amount');

        $totalPm = $transactions
            ->where('transaction_type', PpnTransactionType::Input)
            ->sum('ppn_amount');

        $kurangLebih = round((float) $totalPk - (float) $totalPm, 2);

        $dataJson = [
            'form' => 'SPT Masa PPN 1111',
            'masa_pajak' => sprintf('%02d/%04d', $month, $year),
            'pk_transactions' => $transactions
                ->where('transaction_type', PpnTransactionType::Output)
                ->map(fn ($t) => [
                    'date' => $t->transaction_date->format('Y-m-d'),
                    'counterparty' => $t->counterparty_name,
                    'npwp' => $t->counterparty_npwp,
                    'dpp' => (float) $t->dpp_amount,
                    'ppn' => (float) $t->ppn_amount,
                ])->values()->all(),
            'pm_transactions' => $transactions
                ->where('transaction_type', PpnTransactionType::Input)
                ->map(fn ($t) => [
                    'date' => $t->transaction_date->format('Y-m-d'),
                    'counterparty' => $t->counterparty_name,
                    'npwp' => $t->counterparty_npwp,
                    'dpp' => (float) $t->dpp_amount,
                    'ppn' => (float) $t->ppn_amount,
                ])->values()->all(),
            'summary' => [
                'total_dpp_pk' => round($transactions->where('transaction_type', PpnTransactionType::Output)->sum('dpp_amount'), 2),
                'total_dpp_pm' => round($transactions->where('transaction_type', PpnTransactionType::Input)->sum('dpp_amount'), 2),
            ],
        ];

        return SptMasaPpn::updateOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->default_company_id,
                'year' => $year,
                'month' => $month,
            ],
            [
                'status' => 'DRAFT',
                'total_pk' => round((float) $totalPk, 2),
                'total_pm' => round((float) $totalPm, 2),
                'kurang_lebih_bayar' => $kurangLebih,
                'data_json' => $dataJson,
            ],
        );
    }

    public function finalize(User $user, int $year, int $month): SptMasaPpn
    {
        $this->assertPermission($user, 'fin.tax.spt.finalize');

        $spt = SptMasaPpn::query()
            ->where('year', $year)
            ->where('month', $month)
            ->firstOrFail();

        if ($spt->status === 'FINALIZED') {
            throw new ApiException('SPT already finalized.', 422, 'SPT_ALREADY_FINALIZED');
        }

        $spt->update([
            'status' => 'FINALIZED',
            'finalized_at' => now(),
            'finalized_by' => $user->id,
        ]);

        return $spt->fresh();
    }

    public function show(User $user, int $year, int $month): SptMasaPpn
    {
        $this->assertPermission($user, 'fin.tax.spt.read');

        return SptMasaPpn::query()
            ->where('year', $year)
            ->where('month', $month)
            ->firstOrFail();
    }

    public function list(User $user, ?int $year = null)
    {
        $this->assertPermission($user, 'fin.tax.spt.read');

        $query = SptMasaPpn::query()->orderByDesc('year')->orderByDesc('month');

        if ($year) {
            $query->where('year', $year);
        }

        return $query->get();
    }

    protected function assertPermission(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }
    }
}