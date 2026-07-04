<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Enums\AttendanceStatus;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\AttendanceRecord;
use App\Modules\Business\Models\Employee;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AttendanceAbsentMarkingService
{
    public function __construct(
        protected HrSettingsService $hrSettings,
        protected HrHolidayService $holidays,
        protected HrNotificationService $notifications,
    ) {}

    /**
     * @return array{marked: int, skipped: int, notified: int}
     */
    public function processTenant(Tenant $tenant, bool $force = false, ?string $date = null): array
    {
        $timezone = $tenant->timezone ?: config('app.timezone', 'UTC');
        $now = Carbon::now($timezone);
        $targetDate = $date ?? $now->toDateString();
        $marked = 0;
        $skipped = 0;
        $notified = 0;

        if (! $date && $now->isWeekend()) {
            return ['marked' => 0, 'skipped' => 0, 'notified' => 0];
        }

        $companies = Company::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        foreach ($companies as $company) {
            if ($this->holidays->isHoliday($company, $targetDate)) {
                continue;
            }

            $policy = $this->hrSettings->resolveForCompany($company);

            if (! ($policy['auto_mark_absent'] ?? true)) {
                continue;
            }

            if (! $force && ! $date && ! $this->isPastMarkingCutoff($now, $policy)) {
                continue;
            }

            $result = $this->markCompanyAbsent($tenant, $company, $targetDate);
            $marked += $result['marked'];
            $skipped += $result['skipped'];

            if ($result['marked'] > 0) {
                $this->notifications->notifyDailyAbsentSummary(
                    $tenant->id,
                    $company->id,
                    $targetDate,
                    $result['marked'],
                    $result['names'],
                );
                $notified++;
            }
        }

        return ['marked' => $marked, 'skipped' => $skipped, 'notified' => $notified];
    }

    /**
     * @return array{marked: int, skipped: int, names: list<string>}
     */
    protected function markCompanyAbsent(Tenant $tenant, Company $company, string $date): array
    {
        $employees = Employee::query()
            ->where('company_id', $company->id)
            ->where('status', EmployeeStatus::Active)
            ->get(['id', 'public_id', 'full_name']);

        if ($employees->isEmpty()) {
            return ['marked' => 0, 'skipped' => 0, 'names' => []];
        }

        $records = AttendanceRecord::query()
            ->where('company_id', $company->id)
            ->whereDate('attendance_date', $date)
            ->get()
            ->keyBy('employee_id');

        $marked = 0;
        $skipped = 0;
        $names = [];

        foreach ($employees as $employee) {
            $record = $records->get($employee->id);

            if ($record?->clock_in_at) {
                $skipped++;

                continue;
            }

            if ($record?->status === AttendanceStatus::Leave
                || $record?->status === AttendanceStatus::HalfDay) {
                $skipped++;

                continue;
            }

            if ($record?->status === AttendanceStatus::Absent) {
                $skipped++;

                continue;
            }

            if ($record) {
                $record->update([
                    'status' => AttendanceStatus::Absent,
                    'source' => 'auto',
                    'notes' => trim(($record->notes ? $record->notes."\n" : '').'Otomatis alpa — tidak absen masuk.'),
                ]);
            } else {
                AttendanceRecord::query()->create([
                    'tenant_id' => $tenant->id,
                    'company_id' => $company->id,
                    'public_id' => (string) Str::uuid(),
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                    'status' => AttendanceStatus::Absent,
                    'late_minutes' => 0,
                    'work_minutes' => 0,
                    'source' => 'auto',
                    'notes' => 'Otomatis alpa — tidak absen masuk.',
                ]);
            }

            $marked++;
            $names[] = $employee->full_name;
        }

        return ['marked' => $marked, 'skipped' => $skipped, 'names' => $names];
    }

    protected function isPastMarkingCutoff(Carbon $now, array $policy): bool
    {
        [$hour, $minute] = array_map('intval', explode(':', $policy['work_end']));
        $buffer = (int) ($policy['auto_mark_absent_buffer_minutes'] ?? 30);
        $cutoff = $now->copy()->setTime($hour, $minute, 0)->addMinutes($buffer);

        return $now->gte($cutoff);
    }
}