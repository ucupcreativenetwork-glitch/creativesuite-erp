<?php

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cs_core_permissions')) {
            return;
        }

        $permissions = [
            ['module' => 'int', 'action' => 'read', 'code' => 'int.api_key.read', 'description' => 'View integration API keys'],
            ['module' => 'int', 'action' => 'manage', 'code' => 'int.api_key.manage', 'description' => 'Manage integration API keys'],
            ['module' => 'int', 'action' => 'read', 'code' => 'int.webhook.read', 'description' => 'View webhook endpoints'],
            ['module' => 'int', 'action' => 'manage', 'code' => 'int.webhook.manage', 'description' => 'Manage webhook endpoints'],
            ['module' => 'int', 'action' => 'read', 'code' => 'int.auto_reorder.read', 'description' => 'View auto-reorder rules'],
            ['module' => 'int', 'action' => 'manage', 'code' => 'int.auto_reorder.manage', 'description' => 'Manage auto-reorder rules'],
            ['module' => 'int', 'action' => 'read', 'code' => 'int.connector.read', 'description' => 'View attendance connectors'],
            ['module' => 'int', 'action' => 'manage', 'code' => 'int.connector.manage', 'description' => 'Manage attendance connectors'],
            ['module' => 'int', 'action' => 'run', 'code' => 'int.auto_reorder.run', 'description' => 'Run auto-reorder manually'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(['code' => $permission['code']], $permission);
        }

        if (Schema::hasTable('cs_core_roles')) {
            Role::query()->where('code', 'TENANT_OWNER')->each(function (Role $role): void {
                $role->permissions()->syncWithoutDetaching(Permission::query()->pluck('id'));
            });
        }
    }

    public function down(): void
    {
        //
    }
};