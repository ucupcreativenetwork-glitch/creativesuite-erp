<?php

namespace App\Console\Commands;

use App\Modules\Business\Services\AttendanceReminderService;
use App\Modules\Core\Models\Tenant;
use Illuminate\Console\Command;

class HrSendClockInRemindersCommand extends Command
{
    protected $signature = 'hr:send-clock-in-reminders';

    protected $description = 'Kirim pengingat absen masuk ke karyawan yang belum clock-in';

    public function handle(AttendanceReminderService $service): int
    {
        $totalSent = 0;

        Tenant::query()->each(function (Tenant $tenant) use ($service, &$totalSent): void {
            $result = $service->processTenant($tenant);

            if ($result['sent'] > 0) {
                $this->info("Tenant {$tenant->slug}: {$result['sent']} pengingat dikirim");
                $totalSent += $result['sent'];
            }
        });

        $this->info($totalSent > 0
            ? "Selesai — {$totalSent} pengingat absen masuk terkirim."
            : 'Tidak ada pengingat yang perlu dikirim saat ini.');

        return self::SUCCESS;
    }
}