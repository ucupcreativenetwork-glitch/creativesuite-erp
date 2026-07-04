<?php

namespace App\Console\Commands;

use App\Support\Platform\TenantPurgeService;
use Illuminate\Console\Command;

class PurgeDemoCommand extends Command
{
    protected $signature = 'erp:purge-demo
                            {--slug= : Slug tenant demo (default: pt-demo)}
                            {--force : Lewati konfirmasi}';

    protected $description = 'Hapus tenant demo beserta seluruh data operasionalnya';

    public function handle(TenantPurgeService $purgeService): int
    {
        $slug = $this->option('slug') ?: config('platform.demo_tenant_slug', 'pt-demo');

        if (! $this->option('force') && ! $this->confirm("Hapus permanen tenant '{$slug}' dan semua datanya?", false)) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        $result = $purgeService->purgeBySlug($slug);

        $this->info("Tenant '{$result['tenant_slug']}' ({$result['tenant_name']}) berhasil dihapus.");
        $this->table(['Tabel', 'Baris dihapus'], collect($result['deleted_counts'])
            ->filter(fn ($count) => $count > 0)
            ->map(fn ($count, $table) => [$table, $count])
            ->values()
            ->all());

        return self::SUCCESS;
    }
}