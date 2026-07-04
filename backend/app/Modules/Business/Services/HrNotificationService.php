<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\Employee;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\LeaveRequest;
use App\Modules\Core\Models\User;
use App\Modules\Iam\Services\NotificationDispatcher;
use App\Support\Business\HrLeadership;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HrNotificationService
{
    public function __construct(protected NotificationDispatcher $dispatcher) {}

    public function notifyLeaveSubmitted(LeaveRequest $request): void
    {
        $request->loadMissing(['employee', 'requester']);
        $name = $request->employee?->full_name ?? 'Karyawan';
        $title = 'Pengajuan cuti/izin baru';
        $body = "{$name} mengajukan {$request->request_number} ({$request->total_days} hari). Menunggu persetujuan pimpinan.";

        $approvers = $this->leaveApprovers($request->tenant_id)
            ->reject(fn (User $u) => $u->id === $request->requested_by);

        if ($approvers->isEmpty()) {
            return;
        }

        $this->dispatcher->notifyUsers($approvers, 'HR_LEAVE_PENDING', $title, $body, [
            'leave_public_id' => $request->public_id,
            'request_number' => $request->request_number,
            'href' => '/leave',
        ]);
    }

    public function notifyLeaveApproved(LeaveRequest $request): void
    {
        $request->loadMissing(['employee', 'requester', 'approver']);
        $user = $request->requester;
        if (! $user) {
            return;
        }

        $title = 'Cuti/izin disetujui';
        $body = "Pengajuan {$request->request_number} telah disetujui"
            .($request->approver ? " oleh {$request->approver->full_name}" : '')
            .'.';

        $this->dispatcher->notifyUsers(collect([$user]), 'HR_LEAVE_APPROVED', $title, $body, [
            'leave_public_id' => $request->public_id,
            'request_number' => $request->request_number,
            'href' => '/leave',
        ]);
    }

    public function notifyLeaveRejected(LeaveRequest $request): void
    {
        $request->loadMissing(['requester', 'approver']);
        $user = $request->requester;
        if (! $user) {
            return;
        }

        $title = 'Cuti/izin ditolak';
        $reason = $request->rejection_reason ? " Alasan: {$request->rejection_reason}" : '';
        $body = "Pengajuan {$request->request_number} ditolak.{$reason}";

        $this->dispatcher->notifyUsers(collect([$user]), 'HR_LEAVE_REJECTED', $title, $body, [
            'leave_public_id' => $request->public_id,
            'request_number' => $request->request_number,
            'href' => '/leave',
        ]);
    }

    public function notifyClockInReminder(User $user, string $workStart): void
    {
        $title = 'Pengingat absen masuk';
        $body = "Jam kerja dimulai pukul {$workStart}. Jangan lupa absen masuk hari ini.";

        $this->dispatcher->notifyUsers(collect([$user]), 'HR_ATTENDANCE_REMINDER', $title, $body, [
            'work_start' => $workStart,
            'href' => '/attendance',
        ], sendEmail: false);
    }

    public function notifyLateClockIn(AttendanceRecord $record): void
    {
        if ((int) $record->late_minutes <= 0) {
            return;
        }

        $record->loadMissing('employee');
        $name = $record->employee?->full_name ?? 'Karyawan';
        $title = 'Absen masuk terlambat';
        $body = "{$name} absen masuk terlambat {$record->late_minutes} menit.";

        $leaders = $this->leaveApprovers($record->tenant_id);
        if ($leaders->isEmpty()) {
            return;
        }

        $this->dispatcher->notifyUsers($leaders, 'HR_ATTENDANCE_LATE', $title, $body, [
            'attendance_public_id' => $record->public_id,
            'href' => '/attendance',
        ], sendEmail: false);
    }

    /**
     * @param  list<string>  $employeeNames
     */
    public function notifyDailyAbsentSummary(
        int $tenantId,
        int $companyId,
        string $date,
        int $count,
        array $employeeNames,
    ): void {
        if ($count <= 0) {
            return;
        }

        $leaders = $this->leaveApprovers($tenantId);
        if ($leaders->isEmpty()) {
            return;
        }

        $preview = collect($employeeNames)->take(5)->implode(', ');
        $extra = $count > 5 ? ' dan lainnya' : '';
        $title = 'Ringkasan alpa harian';
        $body = "{$count} karyawan alpa pada {$date}: {$preview}{$extra}.";

        $this->dispatcher->notifyUsers($leaders, 'HR_ATTENDANCE_ABSENT_DAILY', $title, $body, [
            'date' => $date,
            'count' => $count,
            'company_id' => $companyId,
            'href' => '/attendance',
        ], sendEmail: false);
    }

    public function notifyExpiringContracts(int $tenantId, int $withinDays = 30): int
    {
        $today = Carbon::today();
        $until = $today->copy()->addDays($withinDays);

        $employees = Employee::query()
            ->where('tenant_id', $tenantId)
            ->where('status', EmployeeStatus::Active)
            ->whereNotNull('contract_end')
            ->whereBetween('contract_end', [$today->toDateString(), $until->toDateString()])
            ->orderBy('contract_end')
            ->get();

        if ($employees->isEmpty()) {
            return 0;
        }

        $leaders = $this->leaveApprovers($tenantId);
        if ($leaders->isEmpty()) {
            return 0;
        }

        $names = $employees->take(5)->map(fn (Employee $e) => "{$e->full_name} ({$e->contract_end->format('d M Y')})")->implode(', ');
        $extra = $employees->count() > 5 ? ' dan lainnya' : '';
        $title = 'Kontrak karyawan segera berakhir';
        $body = "{$employees->count()} kontrak berakhir dalam {$withinDays} hari: {$names}{$extra}.";

        $this->dispatcher->notifyUsers($leaders, 'HR_CONTRACT_EXPIRING', $title, $body, [
            'count' => $employees->count(),
            'href' => '/payroll',
            'employee_public_ids' => $employees->pluck('public_id')->values()->all(),
        ]);

        return $employees->count();
    }

    protected function leaveApprovers(int $tenantId): Collection
    {
        $leaderCodes = HrLeadership::leaderRoleCodes();

        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) use ($leaderCodes): void {
                $q->whereHas('roles', fn ($r) => $r->whereIn('code', $leaderCodes))
                    ->orWhereHas('roles.permissions', fn ($p) => $p->whereIn('code', ['hr.leave.approve', 'hr.leave.manage']));
            })
            ->get()
            ->unique('id')
            ->values();
    }
}