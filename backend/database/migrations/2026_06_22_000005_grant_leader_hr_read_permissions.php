<?php

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Pimpinan perlu read agar bisa buka halaman cuti & absensi (selain approve/manage). */
    private const READ_PERMS = [
        'hr.leave.read',
        'hr.attendance.read',
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
        $permIds = Permission::query()->whereIn('code', self::READ_PERMS)->pluck('id');

        Role::query()
            ->whereIn('code', $leaderCodes)
            ->each(function (Role $role) use ($permIds): void {
                $role->permissions()->syncWithoutDetaching($permIds);
            });
    }

    public function down(): void
    {
        //
    }
};