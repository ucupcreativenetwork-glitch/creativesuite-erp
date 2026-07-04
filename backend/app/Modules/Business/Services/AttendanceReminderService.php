<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Enums\AttendanceStatus;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\Employee;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tenant;
use App\Modules\Iam\Models\IamNotification;
use Carbon\Carbon;

class AttendanceReminderService
{
    public function __construct(
        protected HrSettingsService $hrSettings,
        protected HrHolidayService $holidays,
        protected HrNotificationService $notifications,
    ) {}

    /**
     * @return array{sent: int, skipped: int}
     */
    public function processTenant(Tenant $tenant): array
    {
        $timezone = $tenant->timezone ?: config('app.timezone', 'UTC');
        $now = Carbon::now($timezone);
        $today = $now->toDateString();
        $sent = 0;
        $skipped = 0;

        if ($now->isWeekend()) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $companies = Company::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        foreach ($companies as $company) {
            if ($this->holidays->isHoliday($company, $today)) {
                continue;
            }

            $policy = $this->hrSettings->resolveForCompany($company);

            if (! ($policy['clock_in_reminder_enabled'] ?? true)) {
                continue;
            }

            if (! $this->isWithinReminderWindow($now, $policy)) {
                continue;
            }

            $result = $this->remindCompany($tenant, $company, $today, $policy);
            $sent += $result['sent'];
            $skipped += $result['skipped'];
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * @return array{sent: int, skipped: int}
     */
    protected function remindCompany(Tenant $tenant, Company $company, string $today, array $policy): array
    {
        $employees = Employee::query()
            ->with('user')
            ->where('company_id', $company->id)
            ->where('status', EmployeeStatus::Active)
            ->whereNotNull('user_id')
            ->get();

        if ($employees->isEmpty()) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $records = AttendanceRecord::query()
            ->where('company_id', $company->id)
            ->whereDate('attendance_date', $today)
            ->get()
            ->keyBy('employee_id');

        $sent = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $user = $employee->user;
            if (! $user || ! $user->is_active) {
                $skipped++;

                continue;
            }

            if (! $user->hasPermission('hr.attendance.clock')) {
                $skipped++;

                continue;
            }

            $record = $records->get($employee->id);
            if ($record?->clock_in_at || $record?->status === AttendanceStatus::Leave) {
                $skipped++;

                continue;
            }

            if ($this->alreadyRemindedToday($user->id, $today)) {
                $skipped++;

                continue;
            }

            $this->notifications->notifyClockInReminder($user, $policy['work_start']);
            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    protected function isWithinReminderWindow(Carbon $now, array $policy): bool
    {
        [$hour, $minute] = array_map('intval', explode(':', $policy['work_start']));
        $lead = (int) ($policy['clock_in_reminder_minutes'] ?? 15);
        $windowStart = $now->copy()->setTime($hour, $minute, 0)->subMinutes($lead);
        $windowEnd = $now->copy()->setTime($hour, $minute, 0);

        return $now->gte($windowStart) && $now->lt($windowEnd);
    }

    protected function alreadyRemindedToday(int $userId, string $date): bool
    {
        return IamNotification::query()
            ->where('user_id', $userId)
            ->where('type', 'HR_ATTENDANCE_REMINDER')
            ->whereDate('sent_at', $date)
            ->exists();
    }
}