<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Concerns\ValidatesTenantRelations;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Enums\LeaveRequestStatus;
use App\Modules\Business\Enums\PayrollRunStatus;
use App\Modules\Business\Enums\AttendanceStatus;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Models\LeaveRequest;
use App\Modules\Business\Models\PayrollLine;
use App\Modules\Business\Models\PayrollRun;
use App\Modules\Finance\Enums\AccountMappingKey;
use App\Modules\Finance\Enums\JournalType;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Services\AccountMappingService;
use App\Modules\Finance\Services\JournalService;
use Carbon\Carbon;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Support\Business\ChecksPermissions;
use App\Support\Business\GeneratesDocumentNumber;
use App\Support\Exceptions\ApiException;
use App\Support\Hr\TerCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayrollService
{
    use ChecksPermissions, GeneratesDocumentNumber, ValidatesTenantRelations;

    public function __construct(
        protected JournalService $journalService,
        protected AccountMappingService $accountMapping,
        protected TerCalculator $terCalculator,
        protected HrSettingsService $hrSettingsService,
    ) {}

    public function listEmployees(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'hr.employee.read');

        $query = Employee::query()->orderBy('full_name');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('employee_number', 'like', "%{$search}%")
                    ->orWhere('device_pin', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function showEmployee(User $user, string $publicId): Employee
    {
        $this->assertPermission($user, 'hr.employee.read');

        return Employee::query()->where('public_id', $publicId)->firstOrFail();
    }

    public function createEmployee(User $user, array $data): Employee
    {
        $this->assertPermission($user, 'hr.employee.create');
        $this->assertUserInTenant($user, $data['user_id'] ?? null);

        $employeeNumber = $data['employee_number']
            ?? $this->generateNumber(
                new Employee,
                $user->tenant_id,
                $user->default_company_id,
                'EMP-',
                'employee_number',
            );

        return Employee::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => $employeeNumber,
            'device_pin' => $data['device_pin'] ?? null,
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'department' => $data['department'] ?? null,
            'base_salary' => $data['base_salary'] ?? 0,
            'allowance_amount' => $data['allowance_amount'] ?? 0,
            'ter_category' => $data['ter_category'] ?? 'A',
            'bpjs_number' => $data['bpjs_number'] ?? null,
            'status' => $data['status'] ?? EmployeeStatus::Active->value,
            'hire_date' => $data['hire_date'] ?? null,
            'contract_type' => $data['contract_type'] ?? null,
            'contract_start' => $data['contract_start'] ?? null,
            'contract_end' => $data['contract_end'] ?? null,
            'user_id' => $data['user_id'] ?? null,
        ]);
    }

    public function updateEmployee(User $user, string $publicId, array $data): Employee
    {
        $this->assertPermission($user, 'hr.employee.update');

        $employee = Employee::query()->where('public_id', $publicId)->firstOrFail();

        if (isset($data['user_id'])) {
            $this->assertUserInTenant($user, $data['user_id']);
        }

        $employee->update(array_filter($data, fn ($v) => $v !== null));

        return $employee->fresh();
    }

    /**
     * @param  list<array{public_id: string, device_pin?: string|null}>  $mappings
     * @return array{updated: int}
     */
    public function bulkUpdateDevicePins(User $user, array $mappings): array
    {
        $this->assertPermission($user, 'hr.employee.update');

        $pins = collect($mappings)
            ->pluck('device_pin')
            ->filter(fn ($pin) => $pin !== null && $pin !== '')
            ->values();

        if ($pins->duplicates()->isNotEmpty()) {
            throw new ApiException('PIN mesin tidak boleh duplikat dalam satu perusahaan.', 422, 'DEVICE_PIN_DUPLICATE');
        }

        $updated = 0;

        foreach ($mappings as $row) {
            $employee = Employee::query()
                ->where('public_id', $row['public_id'])
                ->where('company_id', $user->default_company_id)
                ->firstOrFail();

            $pin = $row['device_pin'] ?? null;
            if ($pin !== null && $pin !== '') {
                $exists = Employee::query()
                    ->where('company_id', $user->default_company_id)
                    ->where('device_pin', $pin)
                    ->where('id', '!=', $employee->id)
                    ->exists();

                if ($exists) {
                    throw new ApiException("PIN {$pin} sudah dipakai karyawan lain.", 422, 'DEVICE_PIN_DUPLICATE');
                }
            }

            $employee->update(['device_pin' => $pin ?: null]);
            $updated++;
        }

        return ['updated' => $updated];
    }

    public function deleteEmployee(User $user, string $publicId): void
    {
        $this->assertPermission($user, 'hr.employee.delete');

        $employee = Employee::query()->where('public_id', $publicId)->firstOrFail();
        $employee->delete();
    }

    public function listPayrollRuns(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'hr.payroll.read');

        $query = PayrollRun::query()->with('journalEntry')->orderByDesc('period_year')->orderByDesc('period_month');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function showPayrollRun(User $user, string $publicId): PayrollRun
    {
        $this->assertPermission($user, 'hr.payroll.read');

        return PayrollRun::query()
            ->where('public_id', $publicId)
            ->with(['lines.employee', 'journalEntry'])
            ->firstOrFail();
    }

    public function createPayrollRun(User $user, array $data): PayrollRun
    {
        $this->assertPermission($user, 'hr.payroll.create');

        $exists = PayrollRun::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->where('period_year', $data['period_year'])
            ->where('period_month', $data['period_month'])
            ->exists();

        if ($exists) {
            throw new ApiException('Payroll run already exists for this period.', 422, 'PAYROLL_PERIOD_EXISTS');
        }

        return PayrollRun::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'public_id' => (string) Str::uuid(),
            'run_number' => $this->generateNumber(
                new PayrollRun,
                $user->tenant_id,
                $user->default_company_id,
                'PAY-',
                'run_number',
            ),
            'period_year' => $data['period_year'],
            'period_month' => $data['period_month'],
            'status' => PayrollRunStatus::Draft,
            'created_by' => $user->id,
        ]);
    }

    public function calculate(User $user, string $publicId): PayrollRun
    {
        $this->assertPermission($user, 'hr.payroll.calculate');

        $run = PayrollRun::query()->where('public_id', $publicId)->firstOrFail();

        if ($run->status === PayrollRunStatus::Posted) {
            throw new ApiException('Posted payroll runs cannot be recalculated.', 422, 'PAYROLL_ALREADY_POSTED');
        }

        return DB::transaction(function () use ($run) {
            $run->lines()->delete();

            $employees = Employee::query()
                ->where('status', EmployeeStatus::Active)
                ->get();

            $company = Company::query()->find($run->company_id);

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;

            $periodStart = Carbon::create($run->period_year, $run->period_month, 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            $payrollConfig = $this->hrSettingsService->payrollConfigForCompany($company);
            $workingDays = (int) $payrollConfig['working_days_per_month'];

            foreach ($employees as $employee) {
                $gross = (float) $employee->base_salary;
                $allowance = (float) ($employee->allowance_amount ?? 0);
                $dailyRate = $workingDays > 0 ? $gross / $workingDays : 0;

                $attendance = AttendanceRecord::query()
                    ->where('employee_id', $employee->id)
                    ->whereYear('attendance_date', $run->period_year)
                    ->whereMonth('attendance_date', $run->period_month)
                    ->get();

                $absentDays = $this->countAbsentWorkingDays($periodStart, $periodEnd, $attendance, $company);
                $unpaidLeaveDays = $this->countUnpaidLeaveWorkingDays($employee->id, $periodStart, $periodEnd);
                $lateMinutes = (int) $attendance->sum('late_minutes');
                $lateBlocks = (int) ceil($lateMinutes / 15);
                $attendanceDeduction = round(
                    ($absentDays * $dailyRate * (float) ($payrollConfig['absent_deduction_multiplier'] ?? 1))
                    + ($lateBlocks * (float) ($payrollConfig['late_deduction_per_15min'] ?? 25000)),
                    2,
                );

                $standardMinutes = (int) config('hr.standard_work_minutes', 480);
                $overtimeMinutes = (int) $attendance
                    ->whereNotNull('clock_out_at')
                    ->sum(fn (AttendanceRecord $record) => max(0, (int) $record->work_minutes - $standardMinutes));
                $hoursPerDay = max(1, $standardMinutes / 60);
                $hourlyRate = $workingDays > 0 ? $gross / ($workingDays * $hoursPerDay) : 0;
                $overtimeAmount = round(
                    ($overtimeMinutes / 60) * $hourlyRate * (float) ($payrollConfig['overtime_multiplier'] ?? 1.5),
                    2,
                );

                $taxable = $gross + $allowance + $overtimeAmount;
                $useTer = (bool) ($payrollConfig['use_ter'] ?? true);
                $terCategory = $employee->ter_category ?? 'A';
                $pph21 = $useTer
                    ? $this->terCalculator->monthlyTax($taxable, $terCategory)
                    : 0;
                $bpjs = round($gross * (float) ($payrollConfig['bpjs_employee_rate'] ?? 0.02), 2);
                $other = round($unpaidLeaveDays * $dailyRate, 2);
                $deductions = $pph21 + $bpjs + $attendanceDeduction + $other;
                $net = round($gross + $allowance + $overtimeAmount - $deductions, 2);

                PayrollLine::create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'gross_salary' => $gross,
                    'allowance_amount' => $allowance,
                    'pph21_amount' => $pph21,
                    'bpjs_amount' => $bpjs,
                    'attendance_deduction' => $attendanceDeduction,
                    'overtime_amount' => $overtimeAmount,
                    'other_deductions' => $other,
                    'net_salary' => $net,
                ]);

                $totalGross += $gross;
                $totalDeductions += $deductions;
                $totalNet += $net;
            }

            $run->update([
                'status' => PayrollRunStatus::Calculated,
                'total_gross' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'total_net' => round($totalNet, 2),
            ]);

            return $run->fresh(['lines.employee']);
        });
    }

    public function post(User $user, string $publicId): PayrollRun
    {
        $this->assertPermission($user, 'hr.payroll.post');

        $run = PayrollRun::query()->where('public_id', $publicId)->with('lines')->firstOrFail();

        if ($run->status !== PayrollRunStatus::Calculated) {
            throw new ApiException('Only calculated payroll runs can be posted.', 422, 'PAYROLL_NOT_CALCULATED');
        }

        return DB::transaction(function () use ($user, $run) {
            if (! $run->journal_entry_id) {
                $journal = $this->journalService->createAuto(
                    $user,
                    JournalType::Payroll,
                    Carbon::create($run->period_year, $run->period_month, 1)->endOfMonth(),
                    $this->buildPayrollJournalLines($run),
                    "Jurnal payroll {$run->run_number}",
                    PayrollRun::class,
                    $run->id,
                    $run->run_number,
                );

                $run->journal_entry_id = $journal->id;
            }

            $run->update([
                'status' => PayrollRunStatus::Posted,
                'posted_at' => now(),
                'journal_entry_id' => $run->journal_entry_id,
            ]);

            return $run->fresh(['lines.employee', 'journalEntry']);
        });
    }

    /**
     * Disburse net salary from posted payroll (Dr Utang Gaji, Cr Bank).
     * Idempotent per payroll run via reference_no DISB-{run_number}.
     */
    public function disbursePayroll(User $user, string $publicId, int $bankAccountId): PayrollRun
    {
        $this->assertPermission($user, 'fin.payment.create');

        $run = PayrollRun::query()->where('public_id', $publicId)->with('lines')->firstOrFail();

        if ($run->status !== PayrollRunStatus::Posted) {
            throw new ApiException('Only posted payroll runs can be disbursed.', 422, 'PAYROLL_NOT_POSTED');
        }

        $totalNet = round($run->lines->sum('net_salary'), 2);
        if ($totalNet <= 0) {
            throw new ApiException('Payroll net amount is zero.', 422, 'PAYROLL_ZERO_NET');
        }

        $this->assertBankAccountInScope($user, $bankAccountId);

        $referenceNo = "DISB-{$run->run_number}";
        $existing = JournalEntry::query()
            ->where('company_id', $run->company_id)
            ->where('reference_no', $referenceNo)
            ->where('journal_type', JournalType::CashOut)
            ->first();

        if ($existing) {
            return $run->fresh(['lines.employee', 'journalEntry']);
        }

        $salaryPayableId = $this->accountMapping->getAccountId($run->company_id, AccountMappingKey::SalaryPayableAccount);

        return DB::transaction(function () use ($user, $run, $totalNet, $salaryPayableId, $bankAccountId, $referenceNo) {
            $this->journalService->createAuto(
                $user,
                JournalType::CashOut,
                Carbon::create($run->period_year, $run->period_month, 1)->endOfMonth(),
                [
                    ['account_id' => $salaryPayableId, 'debit' => $totalNet, 'credit' => 0, 'description' => 'Pembayaran gaji'],
                    ['account_id' => $bankAccountId, 'debit' => 0, 'credit' => $totalNet, 'description' => 'Transfer gaji'],
                ],
                "Disbursement payroll {$run->run_number}",
                PayrollRun::class,
                $run->id,
                $referenceNo,
            );

            return $run->fresh(['lines.employee', 'journalEntry']);
        });
    }

    protected function assertBankAccountInScope(User $user, int $bankAccountId): void
    {
        $exists = ChartOfAccount::query()
            ->where('id', $bankAccountId)
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->where('is_postable', true)
            ->exists();

        if (! $exists) {
            throw new ApiException('Bank account not found in current company.', 422, 'INVALID_BANK_ACCOUNT');
        }
    }

    protected function buildPayrollJournalLines(PayrollRun $run): array
    {
        $companyId = $run->company_id;
        $expenseId = $this->accountMapping->getAccountId($companyId, AccountMappingKey::ExpenseAccount);
        $salaryPayableId = $this->accountMapping->getAccountId($companyId, AccountMappingKey::SalaryPayableAccount);
        $pph21PayableId = $this->accountMapping->getAccountId($companyId, AccountMappingKey::Pph21PayableAccount);
        $bpjsPayableId = $this->accountMapping->getAccountId($companyId, AccountMappingKey::BpjsPayableAccount);

        $company = Company::query()->find($run->company_id);
        $payrollConfig = $this->hrSettingsService->payrollConfigForCompany($company);
        $employerRate = (float) $payrollConfig['bpjs_employer_rate'];
        $totalEmployerBpjs = round($run->lines->sum(
            fn (PayrollLine $line) => (float) $line->gross_salary * $employerRate,
        ), 2);
        $totalEmployeeBpjs = round($run->lines->sum('bpjs_amount'), 2);
        $totalBpjs = round($totalEmployeeBpjs + $totalEmployerBpjs, 2);
        $totalExpense = round($run->lines->sum(
            fn (PayrollLine $line) => (float) $line->net_salary
                + (float) $line->pph21_amount
                + (float) $line->bpjs_amount
        ) + $totalEmployerBpjs, 2);
        $totalNet = round($run->lines->sum('net_salary'), 2);
        $totalPph21 = round($run->lines->sum('pph21_amount'), 2);

        return [
            ['account_id' => $expenseId, 'debit' => $totalExpense, 'credit' => 0, 'description' => 'Beban gaji'],
            ['account_id' => $salaryPayableId, 'debit' => 0, 'credit' => $totalNet, 'description' => 'Utang gaji bersih'],
            ['account_id' => $pph21PayableId, 'debit' => 0, 'credit' => $totalPph21, 'description' => 'Utang PPh 21'],
            ['account_id' => $bpjsPayableId, 'debit' => 0, 'credit' => $totalBpjs, 'description' => 'Utang BPJS (karyawan + perusahaan)'],
        ];
    }

    public function exportBpjs(User $user, string $publicId): array
    {
        $this->assertPermission($user, 'hr.payroll.read');

        $run = PayrollRun::query()
            ->where('public_id', $publicId)
            ->with(['lines.employee'])
            ->firstOrFail();

        if ($run->status === PayrollRunStatus::Draft) {
            throw new ApiException('Hitung payroll terlebih dahulu.', 422, 'PAYROLL_NOT_CALCULATED');
        }

        $company = Company::query()->find($run->company_id);
        $payrollConfig = $this->hrSettingsService->payrollConfigForCompany($company);
        $employerRate = (float) $payrollConfig['bpjs_employer_rate'];
        $employeeRate = (float) $payrollConfig['bpjs_employee_rate'];

        $rows = [['No', 'NIK/BPJS', 'Nama', 'Gaji Pokok', 'Iuran Karyawan', 'Iuran Perusahaan', 'Total']];
        $no = 1;

        foreach ($run->lines as $line) {
            $gross = (float) $line->gross_salary;
            $empBpjs = (float) $line->bpjs_amount;
            $companyBpjs = round($gross * $employerRate, 2);
            $rows[] = [
                $no++,
                $line->employee->bpjs_number ?? '',
                $line->employee->full_name,
                $gross,
                $empBpjs,
                $companyBpjs,
                round($empBpjs + $companyBpjs, 2),
            ];
        }

        $csv = collect($rows)->map(fn ($row) => implode(',', array_map(
            fn ($v) => '"'.str_replace('"', '""', (string) $v).'"',
            $row,
        )))->implode("\n");

        return [
            'filename' => "bpjs-{$run->run_number}.csv",
            'content' => $csv,
            'period_label' => $this->periodLabel($run),
        ];
    }

    public function payslip(User $user, string $runPublicId, int $employeeId): array
    {
        $this->assertPermission($user, 'hr.payroll.read');

        $run = PayrollRun::query()
            ->where('public_id', $runPublicId)
            ->with(['lines' => fn ($q) => $q->where('employee_id', $employeeId)->with('employee')])
            ->firstOrFail();

        if ($run->status === PayrollRunStatus::Draft) {
            throw new ApiException('Hitung payroll terlebih dahulu.', 422, 'PAYROLL_NOT_CALCULATED');
        }

        $line = $run->lines->first();
        if (! $line) {
            throw new ApiException('Slip gaji tidak ditemukan.', 404, 'PAYSLIP_NOT_FOUND');
        }

        return $this->formatPayslip($user, $run, $line);
    }

    public function myPayslips(User $user): array
    {
        $employee = $this->resolveMyEmployee($user);

        $lines = PayrollLine::query()
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($q) => $q->where('status', PayrollRunStatus::Posted))
            ->with('payrollRun')
            ->get()
            ->sortByDesc(fn (PayrollLine $line) => ($line->payrollRun->period_year * 100) + $line->payrollRun->period_month)
            ->values();

        return $lines->map(fn (PayrollLine $line) => [
            'run_public_id' => $line->payrollRun->public_id,
            'run_number' => $line->payrollRun->run_number,
            'period_label' => $this->periodLabel($line->payrollRun),
            'net_salary' => (float) $line->net_salary,
            'posted_at' => $line->payrollRun->posted_at?->toIso8601String(),
        ])->all();
    }

    public function myPayslip(User $user, string $runPublicId): array
    {
        $employee = $this->resolveMyEmployee($user);

        $run = PayrollRun::query()
            ->where('public_id', $runPublicId)
            ->where('status', PayrollRunStatus::Posted)
            ->with(['lines' => fn ($q) => $q->where('employee_id', $employee->id)->with('employee')])
            ->firstOrFail();

        $line = $run->lines->first();
        if (! $line) {
            throw new ApiException('Slip gaji tidak ditemukan.', 404, 'PAYSLIP_NOT_FOUND');
        }

        return $this->formatPayslip($user, $run, $line);
    }

    protected function resolveMyEmployee(User $user): Employee
    {
        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->where('status', EmployeeStatus::Active)
            ->first();

        if (! $employee) {
            $employee = app(EmployeeLinkService::class)->ensureForUser($user);
        }

        if (! $employee) {
            throw new ApiException('Data karyawan belum tersedia.', 422, 'EMPLOYEE_NOT_LINKED');
        }

        return $employee;
    }

    protected function formatPayslip(User $user, PayrollRun $run, PayrollLine $line): array
    {
        return [
            'company_name' => $user->defaultCompany?->name ?? 'CreativeSuite ERP',
            'period_label' => $this->periodLabel($run),
            'run_number' => $run->run_number,
            'employee' => [
                'employee_number' => $line->employee->employee_number,
                'full_name' => $line->employee->full_name,
                'department' => $line->employee->department,
                'job_title' => $line->employee->job_title,
            ],
            'earnings' => [
                'gross_salary' => (float) $line->gross_salary,
                'allowance' => (float) ($line->allowance_amount ?? 0),
                'overtime' => (float) ($line->overtime_amount ?? 0),
            ],
            'tax_info' => [
                'ter_category' => $line->employee->ter_category ?? 'A',
                'ter_rate' => $this->terCalculator->monthlyRate(
                    (float) $line->gross_salary + (float) ($line->allowance_amount ?? 0) + (float) ($line->overtime_amount ?? 0),
                    $line->employee->ter_category ?? 'A',
                ),
            ],
            'deductions' => [
                'bpjs' => (float) $line->bpjs_amount,
                'pph21' => (float) $line->pph21_amount,
                'attendance' => (float) ($line->attendance_deduction ?? 0),
                'other' => (float) $line->other_deductions,
            ],
            'net_salary' => (float) $line->net_salary,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function periodLabel(PayrollRun $run): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return ($months[$run->period_month] ?? $run->period_month).' '.$run->period_year;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AttendanceRecord>  $attendance
     */
    protected function countAbsentWorkingDays(
        Carbon $periodStart,
        Carbon $periodEnd,
        $attendance,
        ?Company $company = null,
    ): float {
        $absentDays = 0.0;
        $cursor = $periodStart->copy();
        $holidayService = $company ? app(HrHolidayService::class) : null;

        while ($cursor->lte($periodEnd)) {
            if ($cursor->isWeekend()) {
                $cursor->addDay();

                continue;
            }

            $dateStr = $cursor->toDateString();

            if ($holidayService && $company && $holidayService->isHoliday($company, $dateStr)) {
                $cursor->addDay();

                continue;
            }
            $dayRecord = $attendance->first(
                fn (AttendanceRecord $record) => $record->attendance_date->format('Y-m-d') === $dateStr,
            );

            if (! $dayRecord) {
                $absentDays += 1;
            } elseif ($dayRecord->status === AttendanceStatus::Absent) {
                $absentDays += 1;
            } elseif ($dayRecord->status === AttendanceStatus::HalfDay) {
                $absentDays += 0.5;
            } elseif ($dayRecord->status === AttendanceStatus::Leave) {
                // Hari cuti/izin disetujui — tidak dihitung alpha.
            }

            $cursor->addDay();
        }

        return $absentDays;
    }

    protected function countUnpaidLeaveWorkingDays(int $employeeId, Carbon $periodStart, Carbon $periodEnd): int
    {
        $unpaidTypes = config('hr.unpaid_leave_types', ['UNPAID']);

        $requests = LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', LeaveRequestStatus::Approved)
            ->whereIn('leave_type', $unpaidTypes)
            ->where('start_date', '<=', $periodEnd->toDateString())
            ->where('end_date', '>=', $periodStart->toDateString())
            ->get(['start_date', 'end_date']);

        $days = 0;
        $cursor = $periodStart->copy();

        while ($cursor->lte($periodEnd)) {
            if ($cursor->isWeekend()) {
                $cursor->addDay();

                continue;
            }

            $dateStr = $cursor->toDateString();
            foreach ($requests as $request) {
                if ($dateStr >= $request->start_date->format('Y-m-d')
                    && $dateStr <= $request->end_date->format('Y-m-d')) {
                    $days++;
                    break;
                }
            }

            $cursor->addDay();
        }

        return $days;
    }
}