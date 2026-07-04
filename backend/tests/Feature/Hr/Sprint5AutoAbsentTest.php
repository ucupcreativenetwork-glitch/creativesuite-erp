<?php

namespace Tests\Feature\Hr;

use App\Modules\Business\Enums\AttendanceStatus;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Services\AttendanceAbsentMarkingService;
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
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class Sprint5AutoAbsentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_command_marks_absent_for_employees_without_clock_in(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$tenant, $company, $employee, $leader] = $this->createScenario();
        $date = '2026-06-20'; // Friday

        $this->artisan('hr:mark-daily-absent', ['--force' => true, '--date' => $date])
            ->assertSuccessful();

        $record = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->first();

        $this->assertNotNull($record);
        $this->assertSame(AttendanceStatus::Absent, $record->status);
        $this->assertSame('auto', $record->source);

        $this->assertDatabaseHas('cs_core_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $leader->id,
            'type' => 'HR_ATTENDANCE_ABSENT_DAILY',
        ]);
    }

    public function test_command_skips_employee_who_clocked_in(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [, $company, $employee] = $this->createScenario();
        $date = '2026-06-20';

        AttendanceRecord::query()->create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'clock_in_at' => Carbon::parse("{$date} 08:05:00", 'Asia/Jakarta'),
            'status' => AttendanceStatus::Present,
            'late_minutes' => 0,
            'work_minutes' => 0,
        ]);

        $this->artisan('hr:mark-daily-absent', ['--force' => true, '--date' => $date])
            ->assertSuccessful();

        $this->assertEquals(1, AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->count());

        $this->assertDatabaseMissing('cs_hr_attendance_records', [
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'status' => AttendanceStatus::Absent->value,
        ]);
    }

    public function test_command_skips_when_auto_mark_disabled(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [, $company, $employee] = $this->createScenario();
        $company->update([
            'settings' => [
                'hr' => [
                    'work_start' => '08:00',
                    'work_end' => '17:00',
                    'auto_mark_absent' => false,
                ],
            ],
        ]);

        $date = '2026-06-20';

        $this->artisan('hr:mark-daily-absent', ['--force' => true, '--date' => $date])
            ->assertSuccessful();

        $this->assertDatabaseMissing('cs_hr_attendance_records', [
            'employee_id' => $employee->id,
            'attendance_date' => $date,
        ]);
    }

    public function test_admin_can_update_auto_absent_settings(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createAdminUser();

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->putJson('/api/v1/hr/settings', [
                'auto_mark_absent' => false,
                'auto_mark_absent_buffer_minutes' => 45,
            ])
            ->assertOk()
            ->assertJsonPath('data.auto_mark_absent', false)
            ->assertJsonPath('data.auto_mark_absent_buffer_minutes', 45);
    }

    public function test_service_respects_weekend_without_force_date(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$tenant, $company, $employee] = $this->createScenario();
        Carbon::setTestNow(Carbon::parse('2026-06-21 18:00:00', 'Asia/Jakarta')); // Sunday

        $result = app(AttendanceAbsentMarkingService::class)->processTenant($tenant);

        $this->assertSame(0, $result['marked']);
        $this->assertDatabaseMissing('cs_hr_attendance_records', [
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * @return array{0: Tenant, 1: Company, 2: Employee, 3: User}
     */
    protected function createScenario(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Auto Absent',
            'slug' => 'auto-absent-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Auto Absent',
            'trade_name' => 'Auto Absent Co',
            'entity_type' => EntityType::Pt,
            'is_active' => true,
            'settings' => [
                'hr' => [
                    'work_start' => '08:00',
                    'work_end' => '17:00',
                    'auto_mark_absent' => true,
                    'auto_mark_absent_buffer_minutes' => 30,
                ],
            ],
        ]);

        Branch::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'code' => 'HQ',
            'name' => 'HQ',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $approvePerm = Permission::query()->where('code', 'hr.leave.approve')->firstOrFail();

        $leaderRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'HEAD_HRD',
            'name' => 'Head HRD',
            'is_system' => false,
            'is_active' => true,
        ]);
        $leaderRole->permissions()->attach($approvePerm->id);

        $leader = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'head@absent.id',
            'password' => 'Password123',
            'full_name' => 'Head HRD',
            'default_company_id' => $company->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $leader->roles()->attach($leaderRole->id, ['tenant_id' => $tenant->id]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $leader->id,
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-AB-01',
            'full_name' => 'Staff Absent',
            'status' => EmployeeStatus::Active,
        ]);

        return [$tenant, $company, $employee, $leader];
    }

    /**
     * @return array{0: Company, 1: User, 2: string}
     */
    protected function createAdminUser(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Admin Absent',
            'slug' => 'admin-absent-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Admin Absent',
            'trade_name' => 'Admin Absent Co',
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
            'email' => 'admin@absent.id',
            'password' => 'Password123',
            'full_name' => 'Admin',
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

        return [$company, $user, JWTAuth::fromUser($user)];
    }
}