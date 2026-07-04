<?php

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Illuminate\Database\Migrations\Migration;

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

            Role::query()->where('code', 'HEAD_HRD')->each(function (Role $role): void {
                $ids = Permission::query()
                    ->whereIn('code', [
                        'hr.attendance.read',
                        'hr.attendance.clock',
                        'hr.attendance.manage',
                        'hr.attendance.report',
                    ])
                    ->pluck('id');
                $role->permissions()->syncWithoutDetaching($ids);
            });
        }
    }

    public function down(): void
    {
        //
    }
};