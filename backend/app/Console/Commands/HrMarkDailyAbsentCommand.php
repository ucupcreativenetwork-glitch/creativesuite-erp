<?php

namespace App\Console\Commands;

use App\Modules\Business\Services\AttendanceAbsentMarkingService;
use App\Modules\Core\Models\Tenant;
use Illuminate\Console\Command;

class HrMarkDailyAbsentCommand extends Command
{
    protected $signature = 'hr:mark-daily-absent
                            {--force : Abaikan batas waktu jam pulang}
                            {--date= : Tanggal tertentu (Y-m-d) untuk backfill}';

    protected $description = 'Tandai alpa otomatis untuk karyawan yang tidak absen masuk setelah jam kerja';

    public function handle(AttendanceAbsentMarkingService $service): int
    {
        $force = (bool) $this->option('force');
        $date = $this->option('date');
        $totalMarked = 0;
        $totalNotified = 0;

        Tenant::query()->each(function (Tenant $tenant) use ($service, $force, $date, &$totalMarked, &$totalNotified): void {
            $result = $service->processTenant($tenant, $force, $date);

            if ($result['marked'] > 0) {
                $this->info("Tenant {$tenant->slug}: {$result['marked']} alpa dicatat, {$result['notified']} notifikasi");
                $totalMarked += $result['marked'];
                $totalNotified += $result['notified'];
            }
        });

        $this->info($totalMarked > 0
            ? "Selesai — {$totalMarked} karyawan ditandai alpa, {$totalNotified} ringkasan dikirim."
            : 'Tidak ada karyawan yang perlu ditandai alpa.');

        return self::SUCCESS;
    }
}