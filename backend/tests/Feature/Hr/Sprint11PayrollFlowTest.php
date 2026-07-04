<?php

namespace Tests\Feature\Hr;

use App\Modules\Business\Enums\AttendanceStatus;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Enums\LeaveRequestStatus;
use App\Modules\Business\Enums\PayrollRunStatus;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Models\LeaveRequest;
use App\Modules\Business\Models\PayrollLine;
use App\Modules\Business\Models\PayrollRun;
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
use App\Modules\Finance\Enums\JournalStatus;
use App\Modules\Finance\Enums\JournalType;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Services\CoaSetupService;
use App\Support\Hr\TerCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class Sprint11PayrollFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_create_payroll_run_in_draft_status(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, , , , $token] = $this->createPayrollScenario();

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson('/api/v1/hr/payroll-runs', [
                'period_year' => 2026,
                'period_month' => 6,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', PayrollRunStatus::Draft->value)
            ->assertJsonPath('data.period_year', 2026)
            ->assertJsonPath('data.period_month', 6);
    }

    public function test_calculate_applies_ter_bpjs_overtime_late_and_absent(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $tenant, $employee, $admin, $token] = $this->createPayrollScenario();
        $employee->update([
            'base_salary' => 11000000,
            'allowance_amount' => 500000,
            'ter_category' => 'A',
        ]);

        $this->seedJune2026Attendance($tenant, $company, $employee, $admin);

        $run = $this->createAndCalculateRun($token, $company);

        $line = PayrollLine::query()
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $payrollConfig = app(\App\Modules\Business\Services\HrSettingsService::class)
            ->payrollConfigForCompany($company->fresh());
        $workingDays = (int) $payrollConfig['working_days_per_month'];
        $dailyRate = 11000000 / $workingDays;
        $hourlyRate = 11000000 / ($workingDays * 8);
        $expectedOvertime = round((60 / 60) * $hourlyRate * (float) $payrollConfig['overtime_multiplier'], 2);
        $expectedLate = 2 * (float) $payrollConfig['late_deduction_per_15min'];
        $expectedAbsent = round($dailyRate * (float) $payrollConfig['absent_deduction_multiplier'], 2);
        $expectedBpjs = round(11000000 * (float) $payrollConfig['bpjs_employee_rate'], 2);
        $taxable = 11000000 + 500000 + $expectedOvertime;
        $expectedPph21 = app(TerCalculator::class)->monthlyTax($taxable, 'A');

        $this->assertEqualsWithDelta(500000, (float) $line->allowance_amount, 0.01);
        $this->assertEqualsWithDelta($expectedBpjs, (float) $line->bpjs_amount, 0.01);
        $this->assertEqualsWithDelta($expectedOvertime, (float) $line->overtime_amount, 0.01);
        $this->assertEqualsWithDelta($expectedPph21, (float) $line->pph21_amount, 0.01);
        $this->assertEqualsWithDelta($expectedAbsent + $expectedLate, (float) $line->attendance_deduction, 0.01);
        $this->assertEqualsWithDelta(0, (float) $line->other_deductions, 0.01);
    }

    public function test_unpaid_leave_deducts_other_deductions(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $tenant, $employee, $admin, $token, $leaderToken] = $this->createPayrollScenario(withLeader: true);
        $employee->update(['base_salary' => 11000000]);

        $this->seedJune2026Attendance($tenant, $company, $employee, $admin, skipDates: ['2026-06-05']);

        $leave = LeaveRequest::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'request_number' => 'LV-UNPAID-01',
            'employee_id' => $employee->id,
            'requested_by' => $employee->user_id,
            'leave_type' => 'UNPAID',
            'start_date' => '2026-06-05',
            'end_date' => '2026-06-05',
            'total_days' => 1,
            'reason' => 'Cuti tanpa gaji',
            'status' => LeaveRequestStatus::Pending,
        ]);

        $leader = User::query()->where('email', 'head@sprint11.id')->firstOrFail();
        app(LeaveService::class)->approve($leader, $leave->public_id);

        $run = $this->createAndCalculateRun($token, $company);

        $line = PayrollLine::query()
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $payrollConfig = app(\App\Modules\Business\Services\HrSettingsService::class)
            ->payrollConfigForCompany($company->fresh());
        $expectedOther = round(11000000 / (int) $payrollConfig['working_days_per_month'], 2);
        $this->assertEqualsWithDelta($expectedOther, (float) $line->other_deductions, 0.01);
    }

    public function test_approved_annual_leave_not_counted_absent(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $tenant, $employee, $admin, $token, $leaderToken] = $this->createPayrollScenario(withLeader: true);
        $employee->update(['base_salary' => 11000000]);

        $this->seedJune2026Attendance($tenant, $company, $employee, $admin, skipDates: ['2026-06-02', '2026-06-05']);

        $leave = LeaveRequest::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'public_id' => (string) Str::uuid(),
            'request_number' => 'LV-ANNUAL-01',
            'employee_id' => $employee->id,
            'requested_by' => $employee->user_id,
            'leave_type' => 'ANNUAL',
            'start_date' => '2026-06-05',
            'end_date' => '2026-06-05',
            'total_days' => 1,
            'reason' => 'Cuti tahunan',
            'status' => LeaveRequestStatus::Pending,
        ]);

        $leader = User::query()->where('email', 'head@sprint11.id')->firstOrFail();
        app(LeaveService::class)->approve($leader, $leave->public_id);

        $run = $this->createAndCalculateRun($token, $company);

        $line = PayrollLine::query()
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $payrollConfig = app(\App\Modules\Business\Services\HrSettingsService::class)
            ->payrollConfigForCompany($company->fresh());
        $dailyRate = 11000000 / (int) $payrollConfig['working_days_per_month'];
        $expectedAbsentDeduction = round($dailyRate * (float) $payrollConfig['absent_deduction_multiplier'], 2);
        $expectedLate = 2 * (float) $payrollConfig['late_deduction_per_15min'];

        $this->assertEqualsWithDelta($expectedAbsentDeduction + $expectedLate, (float) $line->attendance_deduction, 0.01);
        $this->assertLessThan(
            round($dailyRate * 2 * (float) $payrollConfig['absent_deduction_multiplier'], 2) + $expectedLate,
            (float) $line->attendance_deduction,
            'Approved annual leave must not be counted as absence',
        );
        $this->assertTrue(
            AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', '2026-06-05')
                ->where('status', AttendanceStatus::Leave)
                ->exists(),
            'Approved annual leave should create LEAVE attendance on 2026-06-05',
        );
    }

    public function test_post_creates_posted_journal(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $tenant, $employee, $admin, $token] = $this->createPayrollScenario();
        $this->seedJune2026Attendance($tenant, $company, $employee, $admin);

        $run = $this->createAndCalculateRun($token, $company);

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/hr/payroll-runs/{$run->public_id}/post")
            ->assertOk()
            ->assertJsonPath('data.status', PayrollRunStatus::Posted->value);

        $run->refresh();
        $this->assertNotNull($run->journal_entry_id);

        $journal = JournalEntry::query()->findOrFail($run->journal_entry_id);
        $this->assertSame(JournalStatus::Posted, $journal->status);
        $this->assertSame(JournalType::Payroll, $journal->journal_type);
    }

    public function test_posted_payroll_cannot_be_recalculated(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $tenant, $employee, $admin, $token] = $this->createPayrollScenario();
        $this->seedJune2026Attendance($tenant, $company, $employee, $admin);

        $run = $this->createAndCalculateRun($token, $company);

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/hr/payroll-runs/{$run->public_id}/post")
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/hr/payroll-runs/{$run->public_id}/calculate")
            ->assertStatus(422)
            ->assertJsonPath('meta.error_code', 'PAYROLL_ALREADY_POSTED');
    }

    public function test_my_payslips_only_lists_posted_runs(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $tenant, $employee, $admin, $token] = $this->createPayrollScenario();
        $this->seedJune2026Attendance($tenant, $company, $employee, $admin);

        $postedRun = $this->createAndCalculateRun($token, $company, 6);
        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/hr/payroll-runs/{$postedRun->public_id}/post")
            ->assertOk();

        $draftRun = $this->createAndCalculateRun($token, $company, 7);

        $response = $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson('/api/v1/hr/me/payslips')
            ->assertOk();

        $publicIds = collect($response->json('data'))->pluck('run_public_id')->all();
        $this->assertContains($postedRun->public_id, $publicIds);
        $this->assertNotContains($draftRun->public_id, $publicIds);
    }

    public function test_export_bpjs_csv(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $tenant, $employee, $admin, $token] = $this->createPayrollScenario();
        $employee->update(['base_salary' => 11000000, 'bpjs_number' => '1234567890']);
        $this->seedJune2026Attendance($tenant, $company, $employee, $admin);

        $run = $this->createAndCalculateRun($token, $company);

        $response = $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->getJson("/api/v1/hr/payroll-runs/{$run->public_id}/bpjs-export")
            ->assertOk();

        $this->assertStringContainsString('bpjs-', $response->json('data.filename'));
        $this->assertStringContainsString('NIK/BPJS', $response->json('data.content'));
        $this->assertStringContainsString('1234567890', $response->json('data.content'));
        $this->assertStringContainsString('Iuran Karyawan', $response->json('data.content'));
    }

    public function test_disburse_creates_cash_out_journal(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        [$company, $tenant, $employee, $admin, $token] = $this->createPayrollScenario();
        $this->seedJune2026Attendance($tenant, $company, $employee, $admin);

        $run = $this->createAndCalculateRun($token, $company);

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/hr/payroll-runs/{$run->public_id}/post")
            ->assertOk();

        $bank = ChartOfAccount::query()->where('code', '1-12-110')->firstOrFail();

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/hr/payroll-runs/{$run->public_id}/disburse", [
                'bank_account_id' => $bank->id,
            ])
            ->assertOk();

        $journal = JournalEntry::query()
            ->where('company_id', $company->id)
            ->where('reference_no', "DISB-{$run->run_number}")
            ->where('journal_type', JournalType::CashOut)
            ->first();

        $this->assertNotNull($journal);
        $this->assertSame(JournalStatus::Posted, $journal->status);
    }

    protected function createAndCalculateRun(string $token, Company $company, int $month = 6): PayrollRun
    {
        $create = $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson('/api/v1/hr/payroll-runs', [
                'period_year' => 2026,
                'period_month' => $month,
            ])
            ->assertCreated();

        $publicId = $create->json('data.public_id');

        $this->withToken($token)
            ->withHeader('X-Company-ID', $company->public_id)
            ->postJson("/api/v1/hr/payroll-runs/{$publicId}/calculate")
            ->assertOk();

        return PayrollRun::query()->where('public_id', $publicId)->firstOrFail();
    }

    /**
     * @param  list<string>  $skipDates
     */
    protected function seedJune2026Attendance(
        Tenant $tenant,
        Company $company,
        Employee $employee,
        User $admin,
        array $skipDates = ['2026-06-02'],
    ): void {
        $cursor = Carbon::parse('2026-06-01');
        $end = Carbon::parse('2026-06-30');

        while ($cursor->lte($end)) {
            if ($cursor->isWeekend()) {
                $cursor->addDay();

                continue;
            }

            $date = $cursor->toDateString();

            if (in_array($date, $skipDates, true)) {
                $cursor->addDay();

                continue;
            }

            $lateMinutes = $date === '2026-06-03' ? 30 : 0;
            $workMinutes = $date === '2026-06-04' ? 540 : 480;

            AttendanceRecord::query()->create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'public_id' => (string) Str::uuid(),
                'employee_id' => $employee->id,
                'attendance_date' => $date,
                'clock_in_at' => Carbon::parse("{$date} 08:00:00"),
                'clock_out_at' => Carbon::parse("{$date} 17:00:00"),
                'status' => AttendanceStatus::Present,
                'late_minutes' => $lateMinutes,
                'work_minutes' => $workMinutes,
                'source' => 'manual',
                'created_by' => $admin->id,
            ]);

            $cursor->addDay();
        }
    }

    /**
     * @return array{0: Company, 1: Tenant, 2: Employee, 3: User, 4: string, 5?: string, 6?: string}
     */
    protected function createPayrollScenario(bool $withLeader = false, bool $withStaff = false): array
    {
        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::uuid(),
            'name' => 'PT Sprint11',
            'slug' => 'sprint11-co',
            'status' => TenantStatus::Trial,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
        ]);

        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'legal_name' => 'PT Sprint11',
            'trade_name' => 'Sprint11 Co',
            'entity_type' => EntityType::Pt,
            'is_active' => true,
            'settings' => [
                'hr' => [
                    'include_national_holidays' => false,
                ],
            ],
        ]);

        app(CoaSetupService::class)->setupForCompany($tenant->id, $company->id);

        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'code' => 'HQ',
            'name' => 'HQ',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $payrollPerms = Permission::query()
            ->whereIn('code', [
                'hr.payroll.read',
                'hr.payroll.create',
                'hr.payroll.calculate',
                'hr.payroll.post',
                'fin.payment.create',
                'fin.journal.post',
            ])
            ->pluck('id');

        $adminRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'PAYROLL_ADMIN',
            'name' => 'Payroll Admin',
            'is_system' => false,
            'is_active' => true,
        ]);
        $adminRole->permissions()->attach($payrollPerms);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'public_id' => (string) Str::uuid(),
            'email' => 'admin@sprint11.id',
            'password' => 'Password123',
            'full_name' => 'Payroll Admin',
            'default_company_id' => $company->id,
            'default_branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->roles()->attach($adminRole->id, ['tenant_id' => $tenant->id]);

        UserCompanyAccess::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'company_id' => $company->id,
            'is_default' => true,
        ]);

        $staffUser = null;
        $staffToken = null;

        if ($withStaff) {
            $staffRole = Role::query()->create([
                'tenant_id' => $tenant->id,
                'code' => 'STAFF',
                'name' => 'Staff',
                'is_system' => false,
                'is_active' => true,
            ]);

            $staffUser = User::query()->create([
                'tenant_id' => $tenant->id,
                'public_id' => (string) Str::uuid(),
                'email' => 'staff@sprint11.id',
                'password' => 'Password123',
                'full_name' => 'Staff Sprint11',
                'default_company_id' => $company->id,
                'default_branch_id' => $branch->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $staffUser->roles()->attach($staffRole->id, ['tenant_id' => $tenant->id]);

            UserCompanyAccess::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $staffUser->id,
                'company_id' => $company->id,
                'is_default' => true,
            ]);

            $staffToken = JWTAuth::fromUser($staffUser);
        }

        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'user_id' => $staffUser?->id ?? $admin->id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => 'EMP-S11-01',
            'full_name' => 'Staff Sprint11',
            'status' => EmployeeStatus::Active,
            'base_salary' => 11000000,
            'allowance_amount' => 0,
            'ter_category' => 'A',
        ]);

        $leaderToken = null;
        if ($withLeader) {
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
                'email' => 'head@sprint11.id',
                'password' => 'Password123',
                'full_name' => 'Head HRD',
                'default_company_id' => $company->id,
                'default_branch_id' => $branch->id,
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

            $leaderToken = JWTAuth::fromUser($leader);
        }

        $result = [$company, $tenant, $employee, $admin, JWTAuth::fromUser($admin)];
        if ($withLeader) {
            $result[] = $leaderToken;
        }
        if ($withStaff) {
            $result[] = $staffToken;
        }

        return $result;
    }
}