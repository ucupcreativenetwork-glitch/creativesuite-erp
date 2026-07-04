<?php

namespace Tests\Feature\Hr;

use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Enums\LeaveBalanceEntryType;
use App\Modules\Business\Enums\LeaveRequestStatus;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Models\LeaveEntitlement;
use App\Modules\Business\Models\LeaveRequest;
use App\Modules\Business\Services\LeaveAccrualService;
use App\Modules\Business\Services\LeaveService;
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

class Sprint10LeaveBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_preview_days_excludes_weekends_and_holidays(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $employee, $staffToken] = $this->createLeaveScenario();

        $this->withToken($staffToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson('/api/v1/hr/leave-requests/preview-days?start_date=2026-08-17&end_date=2026-08-21')
            ->assertOk()
            ->assertJsonPath('data.working_days', 4)
            ->assertJsonPath('data.calendar_days', 5);

        $service = app(LeaveService::class);
        $this->assertSame(
            4,
            $service->countWorkingLeaveDays(
                $employee,
                Carbon::parse('2026-08-17'),
                Carbon::parse('2026-08-21'),
            ),
        );
    }

    public function test_pro_rata_entitlement_for_mid_year_hire(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [, $employee] = $this->createLeaveScenario(hireDate: '2026-07-01');

        $balance = app(LeaveAccrualService::class)->getBalance($employee, 2026);

        $this->assertEquals(6.0, $balance['entitlement']);
        $this->assertEquals(6.0, $balance['accrued']);
        $this->assertEquals(6.0, $balance['remaining']);

        $this->assertDatabaseHas('cs_hr_leave_entitlements', [
            'employee_id' => $employee->id,
            'year' => 2026,
            'base_entitlement' => 6,
        ]);
    }

    public function test_monthly_accrual_adds_to_balance(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00'));

        [$company, $employee] = $this->createLeaveScenario(accrualMode: 'MONTHLY');

        $this->artisan('hr:accrue-leave-balances')->assertSuccessful();

        $this->assertDatabaseHas('cs_hr_leave_balance_ledger', [
            'employee_id' => $employee->id,
            'year' => 2026,
            'entry_type' => LeaveBalanceEntryType::Accrual->value,
            'days' => 1,
        ]);

        $balance = app(LeaveAccrualService::class)->getBalance($employee, 2026);

        $this->assertEquals(12.0, $balance['entitlement']);
        $this->assertEquals(1.0, $balance['accrued']);
        $this->assertEquals(1.0, $balance['remaining']);

        $result = app(LeaveAccrualService::class)->accrueMonthly($company);
        $this->assertSame(0, $result['accrued']);
    }

    public function test_approve_annual_leave_records_usage(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $employee, , $leaderToken] = $this->createLeaveScenario();

        $leave = LeaveRequest::query()->create([
            'tenant_id' => $employee->tenant_id,
            'company_id' => $employee->company_id,
            'public_id' => (string) Str::uuid(),
            'request_number' => 'LV-S10-01',
            'employee_id' => $employee->id,
            'requested_by' => $employee->user_id,
            'leave_type' => 'ANNUAL',
            'start_date' => '2026-06-23',
            'end_date' => '2026-06-24',
            'total_days' => 2,
            'reason' => 'Cuti tahunan',
            'status' => LeaveRequestStatus::Pending,
        ]);

        $this->withToken($leaderToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/hr/leave-requests/{$leave->public_id}/approve")
            ->assertOk();

        $this->assertDatabaseHas('cs_hr_leave_balance_ledger', [
            'employee_id' => $employee->id,
            'leave_request_id' => $leave->id,
            'entry_type' => LeaveBalanceEntryType::Usage->value,
            'days' => 2,
        ]);

        $balance = app(LeaveAccrualService::class)->getBalance($employee, 2026);
        $this->assertEquals(10.0, $balance['remaining']);
        $this->assertEquals(2.0, $balance['used']);
    }

    public function test_cancel_approved_leave_reverses_balance(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $employee, $staffToken, $leaderToken] = $this->createLeaveScenario();

        $leave = LeaveRequest::query()->create([
            'tenant_id' => $employee->tenant_id,
            'company_id' => $employee->company_id,
            'public_id' => (string) Str::uuid(),
            'request_number' => 'LV-S10-02',
            'employee_id' => $employee->id,
            'requested_by' => $employee->user_id,
            'leave_type' => 'ANNUAL',
            'start_date' => '2026-06-23',
            'end_date' => '2026-06-24',
            'total_days' => 2,
            'reason' => 'Cuti tahunan',
            'status' => LeaveRequestStatus::Pending,
        ]);

        $this->withToken($leaderToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/hr/leave-requests/{$leave->public_id}/approve")
            ->assertOk();

        $this->withToken($staffToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/hr/leave-requests/{$leave->public_id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->assertDatabaseHas('cs_hr_leave_balance_ledger', [
            'employee_id' => $employee->id,
            'leave_request_id' => $leave->id,
            'entry_type' => LeaveBalanceEntryType::Reversal->value,
            'days' => 2,
        ]);

        $balance = app(LeaveAccrualService::class)->getBalance($employee, 2026);
        $this->assertEquals(12.0, $balance['remaining']);
        $this->assertEquals(0.0, $balance['used']);
    }

    public function test_admin_can_adjust_leave_balance(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $employee, , $leaderToken] = $this->createLeaveScenario();

        $this->withToken($leaderToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->putJson("/api/v1/hr/employees/{$employee->public_id}/leave-balance", [
                'year' => 2026,
                'days' => 3,
                'notes' => 'Bonus cuti',
            ])
            ->assertOk()
            ->assertJsonPath('data.adjustment', 3)
            ->assertJsonPath('data.remaining', 15);

        $this->assertDatabaseHas('cs_hr_leave_balance_ledger', [
            'employee_id' => $employee->id,
            'entry_type' => LeaveBalanceEntryType::Adjustment->value,
            'days' => 3,
            'notes' => 'Bonus cuti',
        ]);
    }

    public function test_insufficient_balance_rejects_leave_request(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $employee, $staffToken] = $this->createLeaveScenario();

        LeaveEntitlement::query()->create([
            'tenant_id' => $employee->tenant_id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'year' => 2026,
            'base_entitlement' => 2,
            'carried_forward' => 0,
            'adjustment' => 0,
        ]);

        $this->withToken($staffToken)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson('/api/v1/hr/leave-requests', [
                'leave_type' => 'ANNUAL',
                'start_date' => '2026-06-22',
                'end_date' => '2026-06-26',
                'reason' => 'Terlalu panjang',
            ])
            ->assertStatus(422)
            ->assertJsonPath('meta.error_code', 'INSUFFICIENT_LEAVE_BALANCE');
    }

    /**
     * @return array{0: Company, 1: Employee, 2: string, 3: string}
     */
    protected function createLeaveScenario(
        ?string $hireDate = null,
        string $accrualMode = 'ANNUAL',
    ): array {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Sprint10',
            'slug' => 'sprint10-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Sprint10',
            'trade_name' => 'Sprint10 Co',
            'entity_type' => EntityType::Pt,
            'is_active' => true,
            'settings' => [
                'hr' => [
                    'annual_leave_days' => 12,
                    'max_permission_days' => 1,
                    'leave_carry_forward_max' => 6,
                    'leave_accrual_mode' => $accrualMode,
                    'include_national_holidays' => true,
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

        $approvePerm = Permission::query()->where('code', 'hr.leave.approve')->firstOrFail();
        $createPerm = Permission::query()->where('code', 'hr.leave.create')->firstOrFail();
        $managePerm = Permission::query()->where('code', 'hr.leave.manage')->firstOrFail();
        $readPerm = Permission::query()->where('code', 'hr.leave.read')->firstOrFail();

        $leaderRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'HEAD_HRD',
            'name' => 'Head HRD',
            'is_system' => false,
            'is_active' => true,
        ]);
        $leaderRole->permissions()->attach([$approvePerm->id, $managePerm->id, $readPerm->id]);

        $staffRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'STAFF',
            'name' => 'Staff',
            'is_system' => false,
            'is_active' => true,
        ]);
        $staffRole->permissions()->attach([$createPerm->id, $readPerm->id]);

        $leader = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'head@sprint10.id',
            'password' => 'Password123',
            'full_name' => 'Head HRD',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $leader->roles()->attach($leaderRole->id, ['tenant_id' => $tenant->id]);

        $staffUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'staff@sprint10.id',
            'password' => 'Password123',
            'full_name' => 'Staff Sprint10',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staffUser->roles()->attach($staffRole->id, ['tenant_id' => $tenant->id]);

        foreach ([$leader, $staffUser] as $user) {
            UserCompanyAccess::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'company_id' => $company->id,
                'is_default' => true,
            ]);
        }

        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'user_id' => $staffUser->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-S10-01',
            'full_name' => 'Staff Sprint10',
            'status' => EmployeeStatus::Active,
            'hire_date' => $hireDate,
        ]);

        return [
            $company,
            $employee,
            JWTAuth::fromUser($staffUser),
            JWTAuth::fromUser($leader),
        ];
    }
}