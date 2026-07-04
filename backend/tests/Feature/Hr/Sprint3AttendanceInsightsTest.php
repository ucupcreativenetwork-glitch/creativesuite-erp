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
use App\Modules\Iam\Models\IamNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Sprint3AttendanceInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_manager_can_view_live_attendance_dashboard(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $employee, $managerToken] = $this->createManagerWithEmployee();
        $today = now('Asia/Jakarta')->toDateString();

        AttendanceRecord::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'attendance_date' => $today,
            'clock_in_at' => now('Asia/Jakarta')->setTime(8, 5),
            'status' => 'PRESENT',
            'late_minutes' => 0,
            'work_minutes' => 0,
        ]);

        $this->withToken($managerToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson('/api/v1/hr/attendance/live')
            ->assertOk()
            ->assertJsonPath('data.date', $today)
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.present', 1)
            ->assertJsonPath('data.employees.0.employee.public_id', $employee->public_id)
            ->assertJsonPath('data.employees.0.bucket', 'present');
    }

    public function test_manager_can_export_attendance_csv(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $employee, $managerToken] = $this->createManagerWithEmployee();
        $date = now()->subDay()->toDateString();

        AttendanceRecord::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'clock_in_at' => now()->subDay()->setTime(8, 0),
            'clock_out_at' => now()->subDay()->setTime(17, 0),
            'status' => 'PRESENT',
            'late_minutes' => 0,
            'work_minutes' => 540,
        ]);

        $response = $this->withToken($managerToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson("/api/v1/hr/attendance/export?from_date={$date}&to_date={$date}")
            ->assertOk()
            ->assertJsonPath('data.row_count', 1)
            ->assertJsonStructure(['data' => ['filename', 'content', 'row_count']]);

        $csv = base64_decode($response->json('data.content'), true);
        $this->assertNotFalse($csv);
        $this->assertStringContainsString('Tanggal', $csv);
        $this->assertStringContainsString('Staff Live', $csv);
        $this->assertStringContainsString('EMP-LIVE-01', $csv);
    }

    public function test_staff_without_manage_permission_cannot_access_live_or_export(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $staffToken] = $this->createStaffOnlyUser();

        $this->withToken($staffToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson('/api/v1/hr/attendance/live')
            ->assertStatus(403);

        $this->withToken($staffToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson('/api/v1/hr/attendance/export')
            ->assertStatus(403);
    }

    public function test_late_clock_in_notifies_hr_leaders(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        Carbon::setTestNow(Carbon::parse('2026-06-22 09:30:00', 'Asia/Jakarta'));

        [$company, $employee, $manager, $staffToken] = $this->createManagerAndStaff();
        $company->update([
            'settings' => [
                'hr' => [
                    'work_start' => '08:00',
                    'work_end' => '17:00',
                    'late_grace_minutes' => 0,
                ],
            ],
        ]);

        $this->withToken($staffToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson('/api/v1/hr/attendance/clock-in', [])
            ->assertOk()
            ->assertJsonPath('data.status', 'LATE')
            ->assertJsonPath('data.late_minutes', 90);

        $this->assertDatabaseHas('cs_core_notifications', [
            'tenant_id' => $company->tenant_id,
            'user_id' => $manager->id,
            'type' => 'HR_ATTENDANCE_LATE',
        ]);

        $notification = IamNotification::query()
            ->where('user_id', $manager->id)
            ->where('type', 'HR_ATTENDANCE_LATE')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Staff Late', $notification->body);
        $this->assertSame('/attendance', $notification->payload['href'] ?? null);
    }

    /**
     * @return array{0: Company, 1: Employee, 2: string}
     */
    protected function createManagerWithEmployee(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Live HR',
            'slug' => 'live-hr-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Live HR',
            'trade_name' => 'Live HR Co',
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
            'email' => 'hrd@live.id',
            'password' => 'Password123',
            'full_name' => 'HR Manager',
            'default_company_id' => $company->id,
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

        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-LIVE-01',
            'full_name' => 'Staff Live',
            'status' => EmployeeStatus::Active,
        ]);

        return [$company, $employee, auth('api')->login($manager)];
    }

    /**
     * @return array{0: Company, 1: User, 2: string}
     */
    protected function createStaffOnlyUser(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Staff',
            'slug' => 'staff-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Staff',
            'trade_name' => 'Staff Co',
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

        $clockPerm = Permission::query()->where('code', 'hr.attendance.clock')->firstOrFail();

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'STAFF',
            'name' => 'Staff',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($clockPerm->id);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'staff@staff.id',
            'password' => 'Password123',
            'full_name' => 'Staff Only',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        Employee::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'user_id' => $user->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-ST-01',
            'full_name' => 'Staff Only',
            'status' => EmployeeStatus::Active,
        ]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        return [$company, $user, auth('api')->login($user)];
    }

    /**
     * @return array{0: Company, 1: Employee, 2: User, 3: string}
     */
    protected function createManagerAndStaff(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Notify',
            'slug' => 'notify-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Notify',
            'trade_name' => 'Notify Co',
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

        $approvePerm = Permission::query()->where('code', 'hr.leave.approve')->firstOrFail();
        $clockPerm = Permission::query()->where('code', 'hr.attendance.clock')->firstOrFail();

        $managerRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'HEAD_HRD',
            'name' => 'Head HRD',
            'is_system' => false,
            'is_active' => true,
        ]);
        $managerRole->permissions()->attach($approvePerm->id);

        $staffRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'STAFF',
            'name' => 'Staff',
            'is_system' => false,
            'is_active' => true,
        ]);
        $staffRole->permissions()->attach($clockPerm->id);

        $manager = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'head@notify.id',
            'password' => 'Password123',
            'full_name' => 'Head HRD',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $manager->roles()->attach($managerRole->id, ['tenant_id' => $tenant->id]);

        $staffUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'late@notify.id',
            'password' => 'Password123',
            'full_name' => 'Staff Late',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staffUser->roles()->attach($staffRole->id, ['tenant_id' => $tenant->id]);

        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'user_id' => $staffUser->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-NOT-01',
            'full_name' => 'Staff Late',
            'status' => EmployeeStatus::Active,
        ]);

        foreach ([$manager, $staffUser] as $user) {
            UserCompanyAccess::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'company_id' => $company->id,
                'is_default' => true,
            ]);
        }

        return [$company, $employee, $manager, auth('api')->login($staffUser)];
    }
}