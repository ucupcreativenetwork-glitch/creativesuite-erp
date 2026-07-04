<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Resources\EmployeeResource;
use App\Modules\Core\Models\User;
use App\Support\Exceptions\ApiException;
use App\Support\Tenant\TenantManager;
use Carbon\Carbon;

class HrMeService
{
    public function __construct(
        protected LeaveService $leaveService,
        protected EmployeeLinkService $employeeLinkService,
    ) {}

    public function profile(User $user): array
    {
        $employee = $this->resolveEmployee($user);

        $today = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->where('attendance_date', $this->tenantToday($user))
            ->first();

        return [
            'employee' => (new EmployeeResource($employee))->resolve(),
            'leave_balance' => $this->leaveService->leaveBalanceForUser($user),
            'attendance_today' => $today ? [
                'status' => $today->status?->value ?? $today->status,
                'clock_in_at' => $today->clock_in_at?->toIso8601String(),
                'clock_out_at' => $today->clock_out_at?->toIso8601String(),
                'late_minutes' => (int) $today->late_minutes,
            ] : null,
        ];
    }

    protected function resolveEmployee(User $user): Employee
    {
        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->where('status', EmployeeStatus::Active)
            ->first();

        if (! $employee) {
            $employee = $this->employeeLinkService->ensureForUser($user);
        }

        if (! $employee) {
            throw new ApiException('Data karyawan belum tersedia.', 422, 'EMPLOYEE_NOT_LINKED');
        }

        return $employee;
    }

    protected function tenantToday(User $user): string
    {
        $tenant = app(TenantManager::class)->get() ?? $user->tenant()->first();
        $timezone = $tenant?->timezone ?: config('app.timezone', 'UTC');

        return Carbon::now($timezone)->toDateString();
    }
}