<?php

namespace Tests\Feature\Hr;

use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\Employee;
use App\Modules\Core\Enums\EntityType;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Sprint1HrAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_branch_geofence(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createAdminUser();

        $branch = Branch::query()->where('company_id', $company->id)->firstOrFail();

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->putJson("/api/v1/branches/{$branch->id}", [
                'attendance_geofence_enabled' => true,
                'attendance_latitude' => -6.175100,
                'attendance_longitude' => 106.865000,
                'attendance_geofence_radius_m' => 200,
            ])
            ->assertOk()
            ->assertJsonPath('data.attendance_geofence_enabled', true)
            ->assertJsonPath('data.attendance_geofence_radius_m', 200);

        $this->assertDatabaseHas('cs_core_branches', [
            'id' => $branch->id,
            'attendance_geofence_enabled' => true,
            'attendance_geofence_radius_m' => 200,
        ]);
    }

    public function test_hr_manager_can_adjust_attendance_record(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $employee, $managerToken] = $this->createManagerWithEmployee();

        $record = AttendanceRecord::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
            'clock_in_at' => now()->setTime(9, 30),
            'status' => 'LATE',
            'late_minutes' => 30,
            'work_minutes' => 0,
        ]);

        $this->withToken($managerToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->putJson("/api/v1/hr/attendance/{$record->public_id}/adjust", [
                'clock_in_at' => now()->setTime(8, 0)->toIso8601String(),
                'clock_out_at' => now()->setTime(17, 0)->toIso8601String(),
                'status' => 'PRESENT',
                'notes' => 'Koreksi HRD',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'PRESENT')
            ->assertJsonPath('data.notes', 'Koreksi HRD')
            ->assertJsonPath('data.work_minutes', 540);
    }

    public function test_hr_manager_can_create_manual_attendance(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $employee, $managerToken] = $this->createManagerWithEmployee();
        $date = now()->subDay()->toDateString();

        $this->withToken($managerToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson('/api/v1/hr/attendance/manual', [
                'employee_public_id' => $employee->public_id,
                'attendance_date' => $date,
                'status' => 'PRESENT',
                'clock_in_at' => now()->subDay()->setTime(8, 0)->toIso8601String(),
                'clock_out_at' => now()->subDay()->setTime(17, 0)->toIso8601String(),
                'notes' => 'Lupa absen',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'PRESENT')
            ->assertJsonPath('data.attendance_date', $date);

        $this->assertDatabaseHas('cs_hr_attendance_records', [
            'employee_id' => $employee->id,
            'source' => 'manual',
        ]);
        $this->assertEquals(1, AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->count());
    }

    protected function createAdminUser(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Admin',
            'slug' => 'admin-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Admin',
            'trade_name' => 'Admin Co',
            'entity_type' => EntityType::Pt,
            'is_active' => true,
        ]);

        Branch::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'code' => 'HQ',
            'name' => 'HQ',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $perms = Permission::query()
            ->whereIn('code', ['core.company.read', 'core.company.update'])
            ->pluck('id');

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'ADMIN',
            'name' => 'Admin',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($perms);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'admin@admin.id',
            'password' => 'Password123',
            'full_name' => 'Admin User',
            'default_company_id' => $company->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        return [$company, $user, auth('api')->login($user)];
    }

    protected function createManagerWithEmployee(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT HR',
            'slug' => 'hr-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT HR',
            'trade_name' => 'HR Co',
            'entity_type' => EntityType::Pt,
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'code' => 'HQ',
            'name' => 'HQ',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $managePerm = Permission::query()->where('code', 'hr.attendance.manage')->firstOrFail();

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'HRD',
            'name' => 'HRD',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($managePerm->id);

        $manager = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'hrd@hr.id',
            'password' => 'Password123',
            'full_name' => 'HR Manager',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $manager->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $manager->id,
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        $staffUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'staff@hr.id',
            'password' => 'Password123',
            'full_name' => 'Staff HR',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'user_id' => $staffUser->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-HR-01',
            'full_name' => 'Staff HR',
            'status' => EmployeeStatus::Active,
        ]);

        return [$company, $employee, auth('api')->login($manager)];
    }
}