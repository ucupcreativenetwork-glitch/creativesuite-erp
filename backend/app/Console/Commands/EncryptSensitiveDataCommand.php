<?php

namespace App\Console\Commands;

use App\Support\Security\SensitiveData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EncryptSensitiveDataCommand extends Command
{
    protected $signature = 'erp:encrypt-sensitive {--dry-run : Tampilkan tanpa menyimpan}';

    protected $description = 'Enkripsi data sensitif yang masih tersimpan plaintext di database';

    /** @var array<string, string[]> */
    protected array $tables = [
        'cs_core_companies' => ['npwp', 'nitku', 'phone'],
        'cs_core_users' => ['phone'],
        'cs_hr_employees' => ['bpjs_number', 'phone'],
        'cs_crm_accounts' => ['npwp', 'phone'],
        'cs_int_webhook_endpoints' => ['secret'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach ($this->tables as $table => $attributes) {
            $count = $this->encryptTable($table, $attributes, $dryRun);
            $total += $count;
            $this->line(sprintf('%s: %d field(s)', $table, $count));
        }

        if ($dryRun) {
            $this->warn("Dry-run selesai — {$total} field akan dienkripsi.");
        } else {
            $this->info("Selesai — {$total} field sensitif dienkripsi.");
        }

        return self::SUCCESS;
    }

    protected function encryptTable(string $table, array $attributes, bool $dryRun): int
    {
        $encrypted = 0;

        DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table, $attributes, $dryRun, &$encrypted) {
            foreach ($rows as $row) {
                $updates = [];

                foreach ($attributes as $attribute) {
                    $raw = $row->{$attribute} ?? null;

                    if ($raw === null || $raw === '' || SensitiveData::isEncrypted($raw)) {
                        continue;
                    }

                    $updates[$attribute] = SensitiveData::encrypt($raw);
                    $encrypted++;
                }

                if ($updates !== [] && ! $dryRun) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            }
        });

        return $encrypted;
    }
}