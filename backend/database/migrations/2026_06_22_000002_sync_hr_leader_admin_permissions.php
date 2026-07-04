<?php

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** HR admin — hanya pimpinan (gaji, karyawan, approve cuti, laporan). */
    private const ADMIN_PERMS = [
        'hr.employee.read',
        'hr.employee.create',
        'hr.employee.update',
        'hr.employee.delete',
        'hr.payroll.read',
        'hr.payroll.create',
        'hr.payroll.calculate',
        'hr.payroll.post',
        'hr.leave.approve',
        'hr.leave.manage',
        'hr.attendance.manage',
        'hr.attendance.report',
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

        $leaderCodes = config('hr.leader_role_codes', []);
        $permIds = Permission::query()->whereIn('code', self::ADMIN_PERMS)->pluck('id');

        Role::query()
            ->whereIn('code', $leaderCodes)
            ->each(function (Role $role) use ($permIds): void {
                $role->permissions()->syncWithoutDetaching($permIds);
            });

        Role::query()->where('code', 'TENANT_OWNER')->each(function (Role $role): void {
            $role->permissions()->sync(Permission::query()->pluck('id'));
        });
    }

    public function down(): void
    {
        //
    }
};