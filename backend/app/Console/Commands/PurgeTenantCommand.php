<?php

namespace App\Console\Commands;

use App\Support\Platform\TenantPurgeService;
use Illuminate\Console\Command;

class PurgeTenantCommand extends Command
{
    protected $signature = 'tenant:purge
                            {slug : Slug tenant yang akan dihapus}
                            {--force : Lewati konfirmasi}';

    protected $description = 'Hapus tenant beserta seluruh data operasionalnya (CLI)';

    public function handle(TenantPurgeService $purgeService): int
    {
        $slug = $this->argument('slug');

        if (! $this->option('force') && ! $this->confirm("Hapus permanen tenant '{$slug}' dan semua datanya?", false)) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        $result = $purgeService->purgeBySlug($slug);

        $this->info("Tenant '{$result['tenant_slug']}' berhasil dihapus.");

        return self::SUCCESS;
    }
}