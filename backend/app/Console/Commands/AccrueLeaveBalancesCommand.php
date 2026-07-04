<?php

namespace App\Console\Commands;

use App\Modules\Business\Services\LeaveAccrualService;
use App\Modules\Core\Models\Company;
use Illuminate\Console\Command;

class AccrueLeaveBalancesCommand extends Command
{
    protected $signature = 'hr:accrue-leave-balances';

    protected $description = 'Akrual saldo cuti bulanan untuk karyawan aktif';

    public function handle(LeaveAccrualService $service): int
    {
        $totalProcessed = 0;
        $totalAccrued = 0;

        Company::query()->where('is_active', true)->each(function (Company $company) use ($service, &$totalProcessed, &$totalAccrued): void {
            $result = $service->accrueMonthly($company);

            if ($result['processed'] > 0) {
                $this->info("Company {$company->trade_name}: {$result['accrued']} karyawan diakrual");
                $totalProcessed += $result['processed'];
                $totalAccrued += $result['accrued'];
            }
        });

        $this->info($totalAccrued > 0
            ? "Selesai — {$totalAccrued} akrual cuti dicatat untuk {$totalProcessed} karyawan."
            : 'Tidak ada akrual cuti yang perlu dicatat.');

        return self::SUCCESS;
    }
}