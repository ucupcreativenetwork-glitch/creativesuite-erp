<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Enums\AttendanceStatus;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Enums\LeaveRequestStatus;
use App\Modules\Business\Enums\LeaveType;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Models\LeaveRequest;
use App\Modules\Business\Services\EmployeeLinkService;
use App\Modules\Core\Models\Company as CoreCompany;
use App\Modules\Core\Models\User;
use App\Support\Business\ChecksPermissions;
use App\Support\Business\GeneratesDocumentNumber;
use App\Support\Business\HrLeadership;
use App\Support\Exceptions\ApiException;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaveService
{
    use ChecksPermissions, GeneratesDocumentNumber;

    public function __construct(
        protected HrNotificationService $notifications,
        protected LeaveAccrualService $accrual,
        protected HrHolidayService $holidays,
        protected HrSettingsService $hrSettings,
    ) {}

    public function list(User $user, array $filters = [])
    {
        $this->assertLeaveRead($user);

        $query = LeaveRequest::query()
            ->with(['employee', 'requester', 'approver'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! $this->canManageAll($user)) {
            $employee = Employee::query()->where('user_id', $user->id)->first();
            if ($employee) {
                $query->where('employee_id', $employee->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function show(User $user, string $publicId): LeaveRequest
    {
        $this->assertLeaveRead($user);

        $request = LeaveRequest::query()
            ->where('public_id', $publicId)
            ->with(['employee', 'requester', 'approver'])
            ->firstOrFail();

        if (! $this->canManageAll($user)) {
            $employee = Employee::query()->where('user_id', $user->id)->first();
            if (! $employee || $employee->id !== $request->employee_id) {
                throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
            }
        }

        return $request;
    }

    public function create(User $user, array $data): LeaveRequest
    {
        $this->assertPermission($user, 'hr.leave.create');

        $employee = $this->resolveEmployee($user, $data['employee_public_id'] ?? null);
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        if ($end->lt($start)) {
            throw new ApiException('Tanggal selesai harus setelah tanggal mulai.', 422, 'INVALID_DATE_RANGE');
        }

        $totalDays = $this->countWorkingLeaveDays($employee, $start, $end);

        if ($totalDays <= 0) {
            throw new ApiException('Rentang tanggal tidak mencakup hari kerja.', 422, 'NO_WORKING_DAYS');
        }

        if (($data['leave_type'] ?? null) === LeaveType::Annual->value) {
            $balance = $this->annualLeaveBalanceForEmployee($employee, (int) $start->year);
            if ($totalDays > $balance['remaining']) {
                throw new ApiException(
                    "Saldo cuti tahunan tidak mencukupi. Tersisa {$balance['remaining']} hari.",
                    422,
                    'INSUFFICIENT_LEAVE_BALANCE',
                );
            }
        }

        $company = CoreCompany::query()->findOrFail($employee->company_id);
        $settings = $this->hrSettings->resolveForCompany($company);

        if (($data['leave_type'] ?? null) === LeaveType::Permission->value) {
            $maxPermissionDays = (int) $settings['max_permission_days'];
            if ($totalDays > $maxPermissionDays) {
                throw new ApiException(
                    "Izin maksimal {$maxPermissionDays} hari per pengajuan.",
                    422,
                    'PERMISSION_TOO_LONG',
                );
            }
        }

        $overlap = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::Approved])
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end): void {
                        $q2->where('start_date', '<=', $start)->where('end_date', '>=', $end);
                    });
            })
            ->exists();

        if ($overlap) {
            throw new ApiException('Sudah ada pengajuan cuti/izin pada rentang tanggal tersebut.', 409, 'LEAVE_OVERLAP');
        }

        $leave = LeaveRequest::query()->create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $employee->company_id,
            'public_id' => (string) Str::uuid(),
            'request_number' => $this->generateNumber(
                new LeaveRequest,
                $user->tenant_id,
                $employee->company_id,
                'LV-',
                'request_number',
            ),
            'employee_id' => $employee->id,
            'requested_by' => $user->id,
            'leave_type' => $data['leave_type'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'total_days' => $totalDays,
            'reason' => $data['reason'],
            'status' => LeaveRequestStatus::Pending,
        ])->fresh(['employee', 'requester']);

        $this->notifications->notifyLeaveSubmitted($leave);

        return $leave;
    }

    public function approve(User $user, string $publicId): LeaveRequest
    {
        $this->assertPermission($user, 'hr.leave.approve');

        if (! $this->canApprove($user)) {
            throw new ApiException('Hanya pimpinan yang dapat menyetujui pengajuan cuti/izin.', 403, 'FORBIDDEN');
        }

        $request = LeaveRequest::query()
            ->where('public_id', $publicId)
            ->with('employee')
            ->firstOrFail();

        if ($request->status !== LeaveRequestStatus::Pending) {
            throw new ApiException('Pengajuan tidak dalam status pending.', 422, 'INVALID_STATUS');
        }

        return DB::transaction(function () use ($user, $request) {
            $request->update([
                'status' => LeaveRequestStatus::Approved,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $this->syncAttendanceForLeave($request);

            if ($request->leave_type === LeaveType::Annual) {
                $this->accrual->recordUsage(
                    $request->employee,
                    (int) Carbon::parse($request->start_date)->year,
                    (float) $request->total_days,
                    $request->id,
                );
            }

            $fresh = $request->fresh(['employee', 'requester', 'approver']);
            $this->notifications->notifyLeaveApproved($fresh);

            return $fresh;
        });
    }

    public function reject(User $user, string $publicId, string $reason): LeaveRequest
    {
        $this->assertPermission($user, 'hr.leave.approve');

        if (! $this->canApprove($user)) {
            throw new ApiException('Hanya pimpinan yang dapat menolak pengajuan cuti/izin.', 403, 'FORBIDDEN');
        }

        $request = LeaveRequest::query()->where('public_id', $publicId)->firstOrFail();

        if ($request->status !== LeaveRequestStatus::Pending) {
            throw new ApiException('Pengajuan tidak dalam status pending.', 422, 'INVALID_STATUS');
        }

        $request->update([
            'status' => LeaveRequestStatus::Rejected,
            'rejection_reason' => $reason,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $fresh = $request->fresh(['employee', 'requester', 'approver']);
        $this->notifications->notifyLeaveRejected($fresh);

        return $fresh;
    }

    public function cancel(User $user, string $publicId): LeaveRequest
    {
        if (! $user->hasAnyPermission(['hr.leave.create', 'hr.leave.manage'])) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }

        $request = LeaveRequest::query()->where('public_id', $publicId)->with('employee')->firstOrFail();

        if (! in_array($request->status, [LeaveRequestStatus::Pending, LeaveRequestStatus::Approved], true)) {
            throw new ApiException('Hanya pengajuan pending atau disetujui yang bisa dibatalkan.', 422, 'INVALID_STATUS');
        }

        $employee = Employee::query()->where('user_id', $user->id)->first();
        if (! $this->canManageAll($user) && (! $employee || $employee->id !== $request->employee_id)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }

        return DB::transaction(function () use ($user, $request) {
            if ($request->status === LeaveRequestStatus::Approved) {
                $this->reverseAttendanceForLeave($request);

                if ($request->leave_type === LeaveType::Annual) {
                    $this->accrual->recordReversal(
                        $request->employee,
                        (int) Carbon::parse($request->start_date)->year,
                        (float) $request->total_days,
                        $request->id,
                        $user->id,
                    );
                }
            }

            $request->update(['status' => LeaveRequestStatus::Cancelled]);

            return $request->fresh(['employee', 'requester']);
        });
    }

    public function previewDays(User $user, string $startDate, string $endDate, ?string $employeePublicId = null): array
    {
        $this->assertLeaveRead($user);

        $employee = $this->resolveEmployee($user, $employeePublicId);
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($end->lt($start)) {
            throw new ApiException('Tanggal selesai harus setelah tanggal mulai.', 422, 'INVALID_DATE_RANGE');
        }

        $workingDays = $this->countWorkingLeaveDays($employee, $start, $end);

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'working_days' => $workingDays,
            'calendar_days' => $start->diffInDays($end) + 1,
        ];
    }

    public function leaveBalanceForEmployee(User $user, string $employeePublicId, ?int $year = null): array
    {
        $this->assertLeaveRead($user);

        if (! $this->canManageAll($user)) {
            $ownEmployee = Employee::query()->where('user_id', $user->id)->first();
            $target = Employee::query()->where('public_id', $employeePublicId)->firstOrFail();

            if (! $ownEmployee || $ownEmployee->id !== $target->id) {
                throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
            }
        } else {
            $target = Employee::query()->where('public_id', $employeePublicId)->firstOrFail();
        }

        return $this->annualLeaveBalanceForEmployee($target, $year ?? (int) now()->year);
    }

    public function adjustLeaveBalance(User $user, string $employeePublicId, int $year, float $days, ?string $notes = null): array
    {
        $this->assertPermission($user, 'hr.leave.manage');

        return $this->accrual->adjustBalance($user, $employeePublicId, $year, $days, $notes);
    }

    public function countWorkingLeaveDays(Employee $employee, Carbon $start, Carbon $end): int
    {
        $company = CoreCompany::query()->findOrFail($employee->company_id);
        $count = 0;

        foreach (CarbonPeriod::create($start, $end) as $date) {
            if ($date->isWeekend()) {
                continue;
            }

            if ($this->holidays->isHoliday($company, $date->toDateString())) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    protected function syncAttendanceForLeave(LeaveRequest $request): void
    {
        $company = CoreCompany::query()->findOrFail($request->company_id);
        $period = CarbonPeriod::create($request->start_date, $request->end_date);

        foreach ($period as $date) {
            if ($date->isWeekend() || $this->holidays->isHoliday($company, $date->toDateString())) {
                continue;
            }

            $dateStr = $date->toDateString();
            $record = AttendanceRecord::query()
                ->where('tenant_id', $request->tenant_id)
                ->where('company_id', $request->company_id)
                ->where('employee_id', $request->employee_id)
                ->whereDate('attendance_date', $dateStr)
                ->first();

            if ($record) {
                $record->update([
                    'status' => AttendanceStatus::Leave,
                    'notes' => trim(($record->notes ? $record->notes."\n" : '')."Cuti/Izin: {$request->request_number}"),
                ]);
            } else {
                AttendanceRecord::query()->create([
                    'tenant_id' => $request->tenant_id,
                    'company_id' => $request->company_id,
                    'public_id' => (string) Str::uuid(),
                    'employee_id' => $request->employee_id,
                    'attendance_date' => $dateStr,
                    'status' => AttendanceStatus::Leave,
                    'notes' => "Cuti/Izin: {$request->request_number}",
                    'created_by' => $request->approved_by,
                ]);
            }
        }
    }

    protected function reverseAttendanceForLeave(LeaveRequest $request): void
    {
        $company = CoreCompany::query()->findOrFail($request->company_id);
        $period = CarbonPeriod::create($request->start_date, $request->end_date);
        $marker = "Cuti/Izin: {$request->request_number}";

        foreach ($period as $date) {
            if ($date->isWeekend() || $this->holidays->isHoliday($company, $date->toDateString())) {
                continue;
            }

            $record = AttendanceRecord::query()
                ->where('tenant_id', $request->tenant_id)
                ->where('company_id', $request->company_id)
                ->where('employee_id', $request->employee_id)
                ->whereDate('attendance_date', $date->toDateString())
                ->where('status', AttendanceStatus::Leave)
                ->first();

            if (! $record) {
                continue;
            }

            if ($record->notes === $marker) {
                $record->delete();

                continue;
            }

            $notes = str_replace($marker, '', (string) $record->notes);
            $notes = trim(str_replace("\n\n", "\n", $notes));

            $record->update([
                'status' => AttendanceStatus::Absent,
                'notes' => $notes !== '' ? $notes : null,
            ]);
        }
    }

    protected function resolveEmployee(User $user, ?string $employeePublicId): Employee
    {
        if ($employeePublicId && $this->canManageAll($user)) {
            return Employee::query()
                ->where('public_id', $employeePublicId)
                ->where('status', EmployeeStatus::Active)
                ->firstOrFail();
        }

        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->where('status', EmployeeStatus::Active)
            ->first();

        if (! $employee) {
            $employee = app(EmployeeLinkService::class)->ensureForUser($user);
        }

        if (! $employee || $employee->status !== EmployeeStatus::Active) {
            throw new ApiException('Data karyawan belum tersedia. Pastikan akun aktif dan hubungi HRD.', 422, 'EMPLOYEE_NOT_LINKED');
        }

        return $employee;
    }

    public function myLeaveBalance(User $user): array
    {
        $this->assertLeaveRead($user);

        return $this->leaveBalanceForUser($user);
    }

    public function leaveBalanceForUser(User $user): array
    {
        $employee = Employee::query()->where('user_id', $user->id)->first();
        if (! $employee) {
            $employee = app(EmployeeLinkService::class)->ensureForUser($user);
        }

        if (! $employee) {
            throw new ApiException('Data karyawan belum tersedia.', 422, 'EMPLOYEE_NOT_LINKED');
        }

        return $this->annualLeaveBalanceForEmployee($employee, (int) now()->year);
    }

    public function annualLeaveBalanceForEmployee(Employee $employee, int $year): array
    {
        return $this->accrual->getBalance($employee, $year);
    }

    protected function assertLeaveRead(User $user): void
    {
        if (HrLeadership::isLeader($user)) {
            return;
        }

        if ($user->hasAnyPermission(['hr.leave.read', 'hr.leave.create', 'hr.leave.approve', 'hr.leave.manage'])) {
            return;
        }

        throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
    }

    protected function canManageAll(User $user): bool
    {
        return HrLeadership::canManageHr($user)
            || $user->hasAnyPermission(['hr.leave.approve', 'hr.leave.manage']);
    }

    protected function canApprove(User $user): bool
    {
        return HrLeadership::canApproveLeave($user)
            || $user->hasPermission('hr.leave.manage');
    }
}