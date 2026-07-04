<?php

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Self-service HR for karyawan (absen & ajukan cuti). */
    private const PERMS = [
        'hr.attendance.read',
        'hr.attendance.clock',
        'hr.leave.read',
        'hr.leave.create',
    ];

    /** Role codes dari IamRoleCatalog yang dianggap staff operasional. */
    private const STAFF_ROLE_CODES = [
        'FINANCE_STAFF', 'FINANCE_SUPERVISOR',
        'ACCOUNTING_STAFF', 'ACCOUNTING_SUPERVISOR',
        'HR_STAFF', 'RECRUITER', 'PAYROLL_STAFF',
        'SALES_STAFF', 'SALES_SUPERVISOR',
        'MARKETING_STAFF', 'MARKETING_SUPERVISOR',
        'PURCHASING_STAFF', 'PURCHASING_SUPERVISOR',
        'WAREHOUSE_STAFF', 'WAREHOUSE_SUPERVISOR',
        'TECHNICIAN', 'TECHNICAL_SUPERVISOR',
        'PROJECT_STAFF', 'PROJECT_COORDINATOR', 'PROJECT_SUPERVISOR',
    ];

    public function up(): void
    {
        if (! class_exists(Permission::class) || ! \Illuminate\Support\Facades\Schema::hasTable('cs_core_permissions')) {
            return;
        }

        (new \Database\Seeders\PermissionSeeder)->run();

        if (! \Illuminate\Support\Facades\Schema::hasTable('cs_core_roles')) {
            return;
        }

        $permIds = Permission::query()->whereIn('code', self::PERMS)->pluck('id');

        Role::query()
            ->whereIn('code', self::STAFF_ROLE_CODES)
            ->each(function (Role $role) use ($permIds): void {
                $role->permissions()->syncWithoutDetaching($permIds);
            });
    }

    public function down(): void
    {
        //
    }
};