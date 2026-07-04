<?php

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Iam\Config\RolePermissionCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(Permission::class) || ! \Illuminate\Support\Facades\Schema::hasTable('cs_core_permissions')) {
            return;
        }

        (new \Database\Seeders\PermissionSeeder)->run();

        if (! \Illuminate\Support\Facades\Schema::hasTable('cs_core_roles')) {
            return;
        }

        foreach (RolePermissionCatalog::permissionsByRole() as $roleCode => $permCodes) {
            $role = Role::query()->where('code', $roleCode)->first();
            if (! $role) {
                continue;
            }

            $permIds = Permission::query()->whereIn('code', $permCodes)->pluck('id');
            $role->permissions()->syncWithoutDetaching($permIds);
        }

        Role::query()->where('code', 'TENANT_OWNER')->each(function (Role $role): void {
            $role->permissions()->sync(Permission::query()->pluck('id'));
        });
    }

    public function down(): void
    {
        //
    }
};