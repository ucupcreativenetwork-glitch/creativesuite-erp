<?php

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Ensures phase 2/3 permissions (timesheet, milestone, bank recon) are synced after deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(Permission::class) || ! \Illuminate\Support\Facades\Schema::hasTable('cs_core_permissions')) {
            return;
        }

        (new \Database\Seeders\PermissionSeeder)->run();

        if (\Illuminate\Support\Facades\Schema::hasTable('cs_core_roles')) {
            Role::query()->where('code', 'TENANT_OWNER')->each(function (Role $role): void {
                $role->permissions()->sync(Permission::query()->pluck('id'));
            });
        }
    }

    public function down(): void
    {
        // Permissions are additive; no rollback needed.
    }
};