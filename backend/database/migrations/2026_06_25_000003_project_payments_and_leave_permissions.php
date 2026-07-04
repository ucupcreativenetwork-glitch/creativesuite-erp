<?php

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Iam\Config\IamRoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cs_fin_invoices') && ! Schema::hasColumn('cs_fin_invoices', 'project_id')) {
            Schema::table('cs_fin_invoices', function (Blueprint $table): void {
                $table->unsignedBigInteger('project_id')->nullable()->after('quotation_id');
                $table->index(['tenant_id', 'company_id', 'project_id']);
            });
        }

        if (! class_exists(Permission::class) || ! Schema::hasTable('cs_core_permissions')) {
            return;
        }

        (new \Database\Seeders\PermissionSeeder)->run();

        if (! Schema::hasTable('cs_core_roles')) {
            return;
        }

        $leavePermIds = Permission::query()
            ->whereIn('code', [
                'hr.leave.read', 'hr.leave.create', 'hr.leave.approve', 'hr.leave.manage',
            ])
            ->pluck('id');

        $headCodes = array_values(IamRoleCatalog::HEAD_ROLE_BY_DEPT);
        $headCodes[] = 'DIRECTOR';
        $headCodes[] = 'GENERAL_MANAGER';

        Role::query()
            ->whereIn('code', $headCodes)
            ->each(function (Role $role) use ($leavePermIds): void {
                $role->permissions()->syncWithoutDetaching($leavePermIds);
            });

        Role::query()->where('code', 'TENANT_OWNER')->each(function (Role $role): void {
            $role->permissions()->sync(Permission::query()->pluck('id'));
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('cs_fin_invoices') && Schema::hasColumn('cs_fin_invoices', 'project_id')) {
            Schema::table('cs_fin_invoices', function (Blueprint $table): void {
                $table->dropIndex(['tenant_id', 'company_id', 'project_id']);
                $table->dropColumn('project_id');
            });
        }
    }
};