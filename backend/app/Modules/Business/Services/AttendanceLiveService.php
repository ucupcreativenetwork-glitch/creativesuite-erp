<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Enums\AttendanceStatus;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\Employee;
use App\Modules\Core\Models\User;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;
use App\Support\Tenant\TenantManager;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceLiveService
{
    use ChecksPermissions;

    public function dashboard(User $user): array
    {
        $this->assertAnyPermission($user, ['hr.attendance.manage', 'hr.attendance.report']);

        $today = $this->tenantToday($user);
        $now = $this->tenantNow($user);
        $policy = app(HrSettingsService::class)->resolve($user);

        $employees = Employee::query()
            ->where('company_id', $user->default_company_id)
            ->where('status', EmployeeStatus::Active)
            ->orderBy('full_name')
            ->get(['id', 'public_id', 'employee_number', 'full_name', 'department']);

        $records = AttendanceRecord::query()
            ->where('company_id', $user->default_company_id)
            ->whereDate('attendance_date', $today)
            ->get()
            ->keyBy('employee_id');

        $rows = $employees->map(function (Employee $employee) use ($records, $now, $policy) {
            $record = $records->get($employee->id);
            $bucket = $this->classify($record, $now, $policy);

            return [
                'employee' => [
                    'public_id' => $employee->public_id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->full_name,
                    'department' => $employee->department,
                ],
                'bucket' => $bucket,
                'status' => $record?->status?->value ?? $record?->status,
                'clock_in_at' => $record?->clock_in_at?->toIso8601String(),
                'clock_out_at' => $record?->clock_out_at?->toIso8601String(),
                'late_minutes' => (int) ($record?->late_minutes ?? 0),
                'has_gps' => $record?->clock_in_latitude !== null,
                'has_selfie' => (bool) $record?->clock_in_photo_path,
            ];
        });

        return [
            'date' => $today,
            'as_of' => $now->toIso8601String(),
            'work_start' => $policy['work_start'],
            'work_end' => $policy['work_end'],
            'summary' => $this->summarize($rows),
            'employees' => $rows->values()->all(),
        ];
    }

    protected function classify(?AttendanceRecord $record, Carbon $now, array $policy): string
    {
        if (! $record) {
            return $this->isPastWorkStart($now, $policy) ? 'absent' : 'not_checked_in';
        }

        if ($record->status === AttendanceStatus::Leave) {
            return 'on_leave';
        }

        if ($record->status === AttendanceStatus::HalfDay) {
            return $record->clock_in_at ? 'half_day' : 'half_day';
        }

        if ($record->clock_out_at) {
            return 'completed';
        }

        if ($record->status === AttendanceStatus::Late || $record->late_minutes > 0) {
            return 'late';
        }

        if ($record->clock_in_at) {
            return 'present';
        }

        if ($record->status === AttendanceStatus::Absent) {
            return 'absent';
        }

        return 'not_checked_in';
    }

    protected function isPastWorkStart(Carbon $now, array $policy): bool
    {
        [$hour, $minute] = array_map('intval', explode(':', $policy['work_start']));
        $grace = (int) ($policy['late_grace_minutes'] ?? 15);
        $deadline = $now->copy()->setTime($hour, $minute, 0)->addMinutes($grace);

        return $now->gt($deadline);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    protected function summarize(Collection $rows): array
    {
        $counts = [
            'total' => $rows->count(),
            'present' => 0,
            'late' => 0,
            'completed' => 0,
            'on_leave' => 0,
            'half_day' => 0,
            'absent' => 0,
            'not_checked_in' => 0,
        ];

        foreach ($rows as $row) {
            $bucket = $row['bucket'];
            if (isset($counts[$bucket])) {
                $counts[$bucket]++;
            }
        }

        return $counts;
    }

    protected function tenantToday(User $user): string
    {
        return $this->tenantNow($user)->toDateString();
    }

    protected function tenantNow(User $user): Carbon
    {
        $tenant = app(TenantManager::class)->get() ?? $user->tenant()->first();
        $timezone = $tenant?->timezone ?: config('app.timezone', 'UTC');

        return Carbon::now($timezone);
    }

    protected function assertAnyPermission(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return;
            }
        }

        throw new ApiException('Akses ditolak.', 403, 'FORBIDDEN');
    }
}