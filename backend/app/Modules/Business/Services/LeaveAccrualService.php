<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Enums\LeaveAccrualMode;
use App\Modules\Business\Enums\LeaveBalanceEntryType;
use App\Modules\Business\Enums\LeaveRequestStatus;
use App\Modules\Business\Enums\LeaveType;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Models\LeaveBalanceLedger;
use App\Modules\Business\Models\LeaveEntitlement;
use App\Modules\Business\Models\LeaveRequest;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveAccrualService
{
    public function __construct(protected HrSettingsService $hrSettings) {}

    public function ensureEntitlement(Employee $employee, int $year): LeaveEntitlement
    {
        $existing = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->first();

        if ($existing) {
            return $existing;
        }

        $company = Company::query()->findOrFail($employee->company_id);
        $settings = $this->hrSettings->resolveForCompany($company);
        $annualDays = (float) $settings['annual_leave_days'];
        $baseEntitlement = $this->calculateProRataEntitlement($employee, $year, $annualDays);
        $carriedForward = $this->calculateCarryForward($employee, $year, (int) $settings['leave_carry_forward_max']);

        return LeaveEntitlement::query()->create([
            'tenant_id' => $employee->tenant_id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'year' => $year,
            'base_entitlement' => $baseEntitlement,
            'carried_forward' => $carriedForward,
            'adjustment' => 0,
        ]);
    }

    /**
     * @return array{processed: int, accrued: int}
     */
    public function accrueMonthly(Company $company): array
    {
        $settings = $this->hrSettings->resolveForCompany($company);

        if (($settings['leave_accrual_mode'] ?? LeaveAccrualMode::Annual->value) !== LeaveAccrualMode::Monthly->value) {
            return ['processed' => 0, 'accrued' => 0];
        }

        $year = (int) now()->year;
        $monthKey = now()->format('Y-m');
        $monthlyDays = round((float) $settings['annual_leave_days'] / 12, 2);
        $processed = 0;
        $accrued = 0;

        $employees = Employee::query()
            ->where('company_id', $company->id)
            ->where('status', EmployeeStatus::Active)
            ->get();

        foreach ($employees as $employee) {
            $this->ensureEntitlement($employee, $year);

            $alreadyAccrued = LeaveBalanceLedger::query()
                ->where('employee_id', $employee->id)
                ->where('year', $year)
                ->where('entry_type', LeaveBalanceEntryType::Accrual)
                ->where('notes', "Monthly accrual {$monthKey}")
                ->exists();

            if ($alreadyAccrued) {
                continue;
            }

            $hireDate = $employee->hire_date;
            if ($hireDate && $hireDate->year === $year && $hireDate->format('Y-m') > $monthKey) {
                continue;
            }

            LeaveBalanceLedger::query()->create([
                'tenant_id' => $employee->tenant_id,
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'year' => $year,
                'entry_type' => LeaveBalanceEntryType::Accrual,
                'days' => $monthlyDays,
                'notes' => "Monthly accrual {$monthKey}",
                'created_at' => now(),
            ]);

            $processed++;
            $accrued++;
        }

        return ['processed' => $processed, 'accrued' => $accrued];
    }

    public function getBalance(Employee $employee, int $year): array
    {
        $company = Company::query()->findOrFail($employee->company_id);
        $settings = $this->hrSettings->resolveForCompany($company);
        $entitlement = $this->ensureEntitlement($employee, $year);

        $accrued = (float) LeaveBalanceLedger::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->where('entry_type', LeaveBalanceEntryType::Accrual)
            ->sum('days');

        $used = $this->sumLedgerDays($employee->id, $year, [
            LeaveBalanceEntryType::Usage,
        ]);

        $reversed = $this->sumLedgerDays($employee->id, $year, [
            LeaveBalanceEntryType::Reversal,
        ]);

        $used = max(0, $used - $reversed);

        $pending = (float) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type', LeaveType::Annual->value)
            ->where('status', LeaveRequestStatus::Pending)
            ->where(function ($q) use ($year): void {
                $yearStart = Carbon::create($year, 1, 1)->toDateString();
                $yearEnd = Carbon::create($year, 12, 31)->toDateString();
                $q->whereBetween('start_date', [$yearStart, $yearEnd])
                    ->orWhereBetween('end_date', [$yearStart, $yearEnd]);
            })
            ->sum('total_days');

        $accrualMode = $settings['leave_accrual_mode'] ?? LeaveAccrualMode::Annual->value;
        $baseEntitlement = (float) $entitlement->base_entitlement;
        $carriedForward = (float) $entitlement->carried_forward;
        $adjustment = (float) $entitlement->adjustment;

        if ($accrualMode === LeaveAccrualMode::Monthly->value) {
            $available = $accrued + $carriedForward + $adjustment;
            $entitlementAmount = $baseEntitlement + $carriedForward;
        } else {
            $accrued = $baseEntitlement;
            $available = $baseEntitlement + $carriedForward + $adjustment;
            $entitlementAmount = $available;
        }

        $remaining = max(0, round($available - $used - $pending, 2));

        return [
            'year' => $year,
            'entitlement' => round($entitlementAmount, 2),
            'accrued' => round($accrued, 2),
            'carried_forward' => round($carriedForward, 2),
            'adjustment' => round($adjustment, 2),
            'used' => round($used, 2),
            'pending' => round($pending, 2),
            'remaining' => $remaining,
        ];
    }

    public function recordUsage(Employee $employee, int $year, float $days, int $leaveRequestId): void
    {
        LeaveBalanceLedger::query()->create([
            'tenant_id' => $employee->tenant_id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'year' => $year,
            'entry_type' => LeaveBalanceEntryType::Usage,
            'days' => $days,
            'leave_request_id' => $leaveRequestId,
            'notes' => 'Leave approved',
            'created_at' => now(),
        ]);
    }

    public function recordReversal(Employee $employee, int $year, float $days, int $leaveRequestId, ?int $createdBy = null): void
    {
        LeaveBalanceLedger::query()->create([
            'tenant_id' => $employee->tenant_id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'year' => $year,
            'entry_type' => LeaveBalanceEntryType::Reversal,
            'days' => $days,
            'leave_request_id' => $leaveRequestId,
            'notes' => 'Leave cancelled',
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);
    }

    public function adjustBalance(User $user, string $employeePublicId, int $year, float $days, ?string $notes = null): array
    {
        $employee = Employee::query()
            ->where('public_id', $employeePublicId)
            ->where('company_id', $user->default_company_id)
            ->firstOrFail();

        return DB::transaction(function () use ($user, $employee, $year, $days, $notes) {
            $entitlement = $this->ensureEntitlement($employee, $year);
            $entitlement->update([
                'adjustment' => (float) $entitlement->adjustment + $days,
            ]);

            LeaveBalanceLedger::query()->create([
                'tenant_id' => $employee->tenant_id,
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'year' => $year,
                'entry_type' => LeaveBalanceEntryType::Adjustment,
                'days' => $days,
                'notes' => $notes,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);

            return $this->getBalance($employee, $year);
        });
    }

    protected function calculateProRataEntitlement(Employee $employee, int $year, float $annualDays): float
    {
        $hireDate = $employee->hire_date;

        if (! $hireDate || $hireDate->year < $year) {
            return round($annualDays, 2);
        }

        if ($hireDate->year > $year) {
            return 0;
        }

        $monthsRemaining = 12 - $hireDate->month + 1;

        return round($annualDays * $monthsRemaining / 12, 2);
    }

    protected function calculateCarryForward(Employee $employee, int $year, int $maxCarry): float
    {
        if ($year <= 1 || $maxCarry <= 0) {
            return 0;
        }

        $previousYear = $year - 1;
        $previousEntitlement = LeaveEntitlement::query()
            ->where('employee_id', $employee->id)
            ->where('year', $previousYear)
            ->first();

        if (! $previousEntitlement) {
            return 0;
        }

        $previousBalance = $this->getBalance($employee, $previousYear);

        return round(min((float) $previousBalance['remaining'], (float) $maxCarry), 2);
    }

    /**
     * @param  list<LeaveBalanceEntryType>  $types
     */
    protected function sumLedgerDays(int $employeeId, int $year, array $types): float
    {
        return (float) LeaveBalanceLedger::query()
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->whereIn('entry_type', array_map(fn (LeaveBalanceEntryType $type) => $type->value, $types))
            ->sum('days');
    }
}