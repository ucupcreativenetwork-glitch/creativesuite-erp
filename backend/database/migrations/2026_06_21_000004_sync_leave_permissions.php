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

            $leavePerms = Permission::query()
                ->whereIn('code', [
                    'hr.leave.read', 'hr.leave.create', 'hr.leave.approve', 'hr.leave.manage',
                ])
                ->pluck('id');

            Role::query()->where('code', 'HEAD_HRD')->each(function (Role $role) use ($leavePerms): void {
                $role->permissions()->syncWithoutDetaching($leavePerms);
            });
        }
    }

    public function down(): void
    {
        //
    }
};