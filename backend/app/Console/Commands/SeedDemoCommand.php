<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Tenant;
use Database\Seeders\DemoAgencySeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;

class SeedDemoCommand extends Command
{
    protected $signature = 'erp:seed-demo';

    protected $description = 'Buat ulang tenant demo (pt-demo) beserta data contoh';

    public function handle(): int
    {
        $slug = (string) config('platform.demo_tenant_slug', 'pt-demo');
        $existed = Tenant::query()->where('slug', $slug)->exists();

        $this->call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => DemoAgencySeeder::class, '--force' => true]);

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if (! $tenant) {
            $this->error('Gagal membuat tenant demo.');

            return self::FAILURE;
        }

        if ($existed) {
            $this->info("Tenant demo '{$tenant->name}' sudah ada — tidak diubah.");
        } else {
            $this->info("Tenant demo '{$tenant->name}' berhasil dibuat.");
        }

        $this->table(
            ['Field', 'Nilai'],
            [
                ['Nama perusahaan', 'Demo Agency'],
                ['Email', (string) config('platform.demo_admin_email', 'admin@demo.id')],
                ['Password', (string) config('platform.demo_admin_password', 'Password123')],
            ],
        );

        return self::SUCCESS;
    }
}