<?php

namespace App\Console\Commands;

use App\Modules\Business\Services\HrNotificationService;
use App\Modules\Core\Models\Tenant;
use Illuminate\Console\Command;

class HrNotifyExpiringContractsCommand extends Command
{
    protected $signature = 'hr:notify-expiring-contracts {--days=30 : Notifikasi kontrak yang berakhir dalam N hari}';

    protected $description = 'Kirim notifikasi ke pimpinan untuk kontrak karyawan yang segera berakhir';

    public function handle(HrNotificationService $notifications): int
    {
        $days = (int) $this->option('days');
        $total = 0;

        Tenant::query()->each(function (Tenant $tenant) use ($notifications, $days, &$total): void {
            $count = $notifications->notifyExpiringContracts($tenant->id, $days);
            if ($count > 0) {
                $this->info("Tenant {$tenant->slug}: {$count} kontrak (≤{$days} hari)");
                $total += $count;
            }
        });

        $this->info($total > 0 ? "Selesai — {$total} kontrak dilaporkan." : 'Tidak ada kontrak yang perlu dilaporkan.');

        return self::SUCCESS;
    }
}