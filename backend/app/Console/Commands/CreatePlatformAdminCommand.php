<?php

namespace App\Console\Commands;

use App\Modules\Platform\Services\PlatformAdminService;
use Illuminate\Console\Command;

class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'platform:admin:create
                            {email : Email platform admin}
                            {password : Password platform admin}
                            {--name=Platform Administrator : Nama lengkap admin}';

    protected $description = 'Buat atau perbarui akun platform superadmin (tenant slug: platform)';

    public function handle(PlatformAdminService $service): int
    {
        $user = $service->createOrUpdateAdmin(
            $this->argument('email'),
            $this->argument('password'),
            $this->option('name'),
        );

        $slug = config('platform.tenant_slug', 'platform');

        $this->info('Platform admin siap digunakan.');
        $this->table(
            ['Field', 'Value'],
            [
                ['Tenant slug', $slug],
                ['Email', $user->email],
                ['Password', $this->argument('password')],
                ['Nama', $user->full_name],
            ],
        );
        $this->comment('Login: tenant slug "'.$slug.'" + email + password di halaman login.');

        return self::SUCCESS;
    }
}