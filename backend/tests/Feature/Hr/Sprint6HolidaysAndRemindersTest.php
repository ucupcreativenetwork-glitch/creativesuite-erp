<?php

namespace Tests\Feature\Hr;

use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Services\AttendanceReminderService;
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

class Sprint6HolidaysAndRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_holiday_skips_auto_absent_marking(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [, $company, $employee] = $this->createEmployeeScenario();
        $date = '2026-08-17';

        $company->update([
            'settings' => [
                'hr' => [
                    'work_start' => '08:00',
                    'work_end' => '17:00',
                    'auto_mark_absent' => true,
                    'holidays' => [
                        ['date' => $date, 'name' => 'Hari Kemerdekaan'],
                    ],
                ],
            ],
        ]);

        $this->artisan('hr:mark-daily-absent', ['--force' => true, '--date' => $date])
            ->assertSuccessful();

        $this->assertDatabaseMissing('cs_hr_attendance_records', [
            'employee_id' => $employee->id,
        ]);
    }

    public function test_admin_can_save_company_holidays(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , $token] = $this->createAdminUser();

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->putJson('/api/v1/hr/settings', [
                'holidays' => [
                    ['date' => '2026-12-25', 'name' => 'Natal'],
                    ['date' => '2026-01-01', 'name' => 'Tahun Baru'],
                ],
                'clock_in_reminder_enabled' => true,
                'clock_in_reminder_minutes' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('data.holidays.0.name', 'Tahun Baru')
            ->assertJsonPath('data.holidays.1.name', 'Natal')
            ->assertJsonPath('data.clock_in_reminder_minutes', 20);
    }

    public function test_clock_in_reminder_sent_during_window(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$tenant, , , $staffUser] = $this->createEmployeeScenario();
        Carbon::setTestNow(Carbon::parse('2026-06-19 07:50:00', 'Asia/Jakarta'));

        $result = app(AttendanceReminderService::class)->processTenant($tenant);

        $this->assertSame(1, $result['sent']);

        $this->assertDatabaseHas('cs_core_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $staffUser->id,
            'type' => 'HR_ATTENDANCE_REMINDER',
        ]);
    }

    public function test_clock_in_reminder_not_sent_twice_same_day(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$tenant] = $this->createEmployeeScenario();
        Carbon::setTestNow(Carbon::parse('2026-06-19 07:50:00', 'Asia/Jakarta'));

        $service = app(AttendanceReminderService::class);
        $first = $service->processTenant($tenant);
        $second = $service->processTenant($tenant);

        $this->assertSame(1, $first['sent']);
        $this->assertSame(0, $second['sent']);
    }

    public function test_clock_in_reminder_skips_on_holiday(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$tenant, $company, $staffUser] = $this->createEmployeeScenario();
        $company->update([
            'settings' => [
                'hr' => [
                    'work_start' => '08:00',
                    'work_end' => '17:00',
                    'clock_in_reminder_enabled' => true,
                    'clock_in_reminder_minutes' => 15,
                    'holidays' => [
                        ['date' => '2026-06-19', 'name' => 'Libur Perusahaan'],
                    ],
                ],
            ],
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-19 07:50:00', 'Asia/Jakarta'));

        $result = app(AttendanceReminderService::class)->processTenant($tenant);

        $this->assertSame(0, $result['sent']);
        $this->assertDatabaseMissing('cs_core_notifications', [
            'user_id' => $staffUser->id,
            'type' => 'HR_ATTENDANCE_REMINDER',
        ]);
    }

    /**
     * @return array{0: Tenant, 1: Company, 2: Employee, 3: User}
     */
    protected function createEmployeeScenario(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Reminder',
            'slug' => 'reminder-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Reminder',
            'trade_name' => 'Reminder Co',
            'entity_type' => EntityType::Pt,
            'is_active' => true,
            'settings' => [
                'hr' => [
                    'work_start' => '08:00',
                    'work_end' => '17:00',
                    'clock_in_reminder_enabled' => true,
                    'clock_in_reminder_minutes' => 15,
                    'auto_mark_absent' => true,
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

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'STAFF',
            'name' => 'Staff',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($clockPerm->id);

        $staffUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'staff@reminder.id',
            'password' => 'Password123',
            'full_name' => 'Staff Reminder',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staffUser->roles()->attach($role->id, ['tenant_id' => $tenant->id]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staffUser->id,
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'user_id' => $staffUser->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-RM-01',
            'full_name' => 'Staff Reminder',
            'status' => EmployeeStatus::Active,
        ]);

        return [$tenant, $company, $employee, $staffUser];
    }

    /**
     * @return array{0: Company, 1: User, 2: string}
     */
    protected function createAdminUser(): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Admin Holiday',
            'slug' => 'admin-holiday-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Admin Holiday',
            'trade_name' => 'Admin Holiday Co',
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
            'email' => 'admin@holiday.id',
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