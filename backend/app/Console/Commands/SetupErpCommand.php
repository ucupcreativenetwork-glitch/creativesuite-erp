<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Illuminate\Console\Command;

class SetupErpCommand extends Command
{
    protected $signature = 'erp:setup {--fresh : Run migrate:fresh before setup}';

    protected $description = 'Run migrations, seed permissions, and sync TENANT_OWNER role';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true]);
        } else {
            $this->call('migrate', ['--force' => true]);
        }

        $this->call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder', '--force' => true]);
        $this->call('erp:seed-demo');

        $count = Role::query()->where('code', 'TENANT_OWNER')->count();
        Role::query()->where('code', 'TENANT_OWNER')->each(function (Role $role): void {
            $role->permissions()->sync(Permission::query()->pluck('id'));
        });

        $this->info("ERP setup complete. Synced permissions to {$count} TENANT_OWNER role(s).");

        return self::SUCCESS;
    }
}