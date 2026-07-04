<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Enums\LeaveRequestStatus;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Models\LeaveRequest;
use App\Modules\Core\Models\User;
use App\Support\Business\ChecksPermissions;
use App\Support\Business\HrLeadership;
use App\Support\Exceptions\ApiException;
use Carbon\Carbon;

class HrSummaryService
{
    use ChecksPermissions;

    public function summary(User $user): array
    {
        if (! HrLeadership::isLeader($user) && ! $user->hasPermission('hr.employee.read')) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }

        $today = Carbon::today();
        $expiryUntil = $today->copy()->addDays(30);

        $expiring = Employee::query()
            ->where('status', EmployeeStatus::Active)
            ->whereNotNull('contract_end')
            ->whereBetween('contract_end', [$today->toDateString(), $expiryUntil->toDateString()])
            ->orderBy('contract_end')
            ->limit(10)
            ->get(['public_id', 'full_name', 'employee_number', 'contract_end', 'contract_type']);

        return [
            'pending_leave' => LeaveRequest::query()
                ->where('status', LeaveRequestStatus::Pending)
                ->count(),
            'expiring_contracts_count' => Employee::query()
                ->where('status', EmployeeStatus::Active)
                ->whereNotNull('contract_end')
                ->whereBetween('contract_end', [$today->toDateString(), $expiryUntil->toDateString()])
                ->count(),
            'zero_salary_count' => Employee::query()
                ->where('status', EmployeeStatus::Active)
                ->where('base_salary', '<=', 0)
                ->count(),
            'active_employees' => Employee::query()
                ->where('status', EmployeeStatus::Active)
                ->count(),
            'expiring_contracts' => $expiring->map(fn (Employee $e) => [
                'public_id' => $e->public_id,
                'full_name' => $e->full_name,
                'employee_number' => $e->employee_number,
                'contract_type' => $e->contract_type?->value ?? $e->contract_type,
                'contract_end' => $e->contract_end?->format('Y-m-d'),
            ])->values()->all(),
        ];
    }
}