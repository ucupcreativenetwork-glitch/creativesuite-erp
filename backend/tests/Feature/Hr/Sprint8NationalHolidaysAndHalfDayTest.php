<?php

namespace Tests\Feature\Hr;

use App\Modules\Business\Enums\AttendanceStatus;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Services\AttendanceAbsentMarkingService;
use App\Modules\Business\Services\AttendanceReminderService;
use App\Modules\Business\Services\HrHolidayService;
use App\Modules\Business\Services\PayrollService;
use App\Modules\Core\Enums\EntityType;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyAccess;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class Sprint8NationalHolidaysAndHalfDayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_national_holiday_detected_without_company_entry(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [, $company] = $this->createEmployeeScenario();

        $this->assertTrue(app(HrHolidayService::class)->isHoliday($company, '2026-08-17'));
        $this->assertSame(
            'Hari Proklamasi Kemerdekaan',
            app(HrHolidayService::class)->holidayName($company, '2026-08-17'),
        );
    }

    public function test_national_holiday_skips_auto_absent_marking(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$tenant, $company, $employee] = $this->createEmployeeScenario();
        $date = '2026-08-17';

        $this->artisan('hr:mark-daily-absent', ['--force' => true, '--date' => $date])
            ->assertSuccessful();

        $this->assertDatabaseMissing('cs_hr_attendance_records', [
            'employee_id' => $employee->id,
        ]);
    }

    public function test_national_holiday_skips_clock_in_reminder(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$tenant, , , $staffUser] = $this->createEmployeeScenario();
        Carbon::setTestNow(Carbon::parse('2026-08-17 07:50:00', 'Asia/Jakarta'));

        $result = app(AttendanceReminderService::class)->processTenant($tenant);

        $this->assertSame(0, $result['sent']);
        $this->assertDatabaseMissing('cs_core_notifications', [
            'user_id' => $staffUser->id,
            'type' => 'HR_ATTENDANCE_REMINDER',
        ]);
    }

    public function test_admin_can_disable_national_holidays(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createAdminUser();

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->putJson('/api/v1/hr/settings', [
                'include_national_holidays' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.include_national_holidays', false)
            ->assertJsonPath('data.national_holidays', []);

        $company->refresh();
        $this->assertFalse(app(HrHolidayService::class)->isHoliday($company, '2026-08-17'));
    }

    public function test_half_day_skips_auto_absent_marking(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$tenant, $company, $employee, $admin] = $this->createEmployeeScenario();
        $date = '2026-06-10';

        AttendanceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'status' => AttendanceStatus::HalfDay,
            'late_minutes' => 0,
            'work_minutes' => 240,
            'source' => 'manual',
            'created_by' => $admin->id,
        ]);

        $result = app(AttendanceAbsentMarkingService::class)->processTenant($tenant, true, $date);

        $this->assertSame(0, $result['marked']);
        $this->assertSame(AttendanceStatus::HalfDay, AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->value('status'));
    }

    public function test_half_day_counts_as_partial_absence_in_payroll(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [, $company, $employee, $admin] = $this->createEmployeeScenario();

        AttendanceRecord::query()->create([
            'tenant_id' => $employee->tenant_id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'attendance_date' => '2026-06-10',
            'status' => AttendanceStatus::HalfDay,
            'late_minutes' => 0,
            'work_minutes' => 240,
            'source' => 'manual',
            'created_by' => $admin->id,
        ]);

        $service = app(PayrollService::class);
        $method = new \ReflectionMethod(PayrollService::class, 'countAbsentWorkingDays');
        $method->setAccessible(true);

        $periodStart = Carbon::parse('2026-06-10');
        $periodEnd = Carbon::parse('2026-06-10');
        $attendance = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->get();

        $absentDays = $method->invoke($service, $periodStart, $periodEnd, $attendance, $company);

        $this->assertEqualsWithDelta(0.5, $absentDays, 0.001);
    }

    /**
     * @return array{0: Tenant, 1: Company, 2: Employee, 3: User}
     */
    protected function createEmployeeScenario(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Sprint8',
            'slug' => 'sprint8-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Sprint8',
            'trade_name' => 'Sprint8 Co',
            'entity_type' => EntityType::Pt,
            'is_active' => true,
            'settings' => [
                'hr' => [
                    'work_start' => '08:00',
                    'work_end' => '17:00',
                    'include_national_holidays' => true,
                    'auto_mark_absent' => true,
                    'clock_in_reminder_enabled' => true,
                    'clock_in_reminder_minutes' => 15,
                ],
            ],
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
        $payrollPerm = Permission::query()->where('code', 'hr.payroll.calculate')->firstOrFail();

        $staffRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'STAFF',
            'name' => 'Staff',
            'is_system' => false,
            'is_active' => true,
        ]);
        $staffRole->permissions()->attach($clockPerm->id);

        $adminRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'ADMIN',
            'name' => 'Admin',
            'is_system' => false,
            'is_active' => true,
        ]);
        $adminRole->permissions()->attach($payrollPerm->id);

        $staffUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'staff@sprint8.id',
            'password' => 'Password123',
            'full_name' => 'Staff Sprint8',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staffUser->roles()->attach($staffRole->id, ['tenant_id' => $tenant->id]);

        $adminUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'admin@sprint8.id',
            'password' => 'Password123',
            'full_name' => 'Admin Sprint8',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $adminUser->roles()->attach($adminRole->id, ['tenant_id' => $tenant->id]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staffUser->id,
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $adminUser->id,
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'user_id' => $staffUser->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-S8-01',
            'full_name' => 'Staff Sprint8',
            'status' => EmployeeStatus::Active,
            'base_salary' => 11000000,
        ]);

        return [$tenant, $company, $employee, $adminUser];
    }

    /**
     * @return array{0: Company, 1: User, 2: string}
     */
    protected function createAdminUser(): array
    {
        [, $company, , $admin] = $this->createEmployeeScenario();

        $perms = Permission::query()
            ->whereIn('code', ['core.company.read', 'core.company.update'])
            ->pluck('id');

        $role = Role::query()->create([
            'tenant_id' => $admin->tenant_id,
            'code' => 'HR_ADMIN',
            'name' => 'HR Admin',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($perms);
        $admin->roles()->attach($role->id, ['tenant_id' => $admin->tenant_id]);

        return [$company, $admin, JWTAuth::fromUser($admin)];
    }
}