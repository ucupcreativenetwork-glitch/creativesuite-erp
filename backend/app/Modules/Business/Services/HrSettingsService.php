<?php

namespace App\Modules\Business\Services;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Support\Business\ChecksPermissions;
use App\Support\Exceptions\ApiException;

class HrSettingsService
{
    use ChecksPermissions;

    public function get(User $user): array
    {
        $this->assertAnyPermission($user, ['core.company.read', 'hr.attendance.read', 'hr.attendance.manage']);

        return $this->resolve($user);
    }

    public function update(User $user, array $data): array
    {
        $this->assertPermission($user, 'core.company.update');

        $company = $this->companyFor($user);
        $settings = $company->settings ?? [];
        $hr = $settings['hr'] ?? [];

        if (isset($data['work_start'])) {
            $hr['work_start'] = $data['work_start'];
        }
        if (isset($data['work_end'])) {
            $hr['work_end'] = $data['work_end'];
        }
        if (isset($data['late_grace_minutes'])) {
            $hr['late_grace_minutes'] = (int) $data['late_grace_minutes'];
        }
        if (array_key_exists('auto_mark_absent', $data)) {
            $hr['auto_mark_absent'] = (bool) $data['auto_mark_absent'];
        }
        if (isset($data['auto_mark_absent_buffer_minutes'])) {
            $hr['auto_mark_absent_buffer_minutes'] = (int) $data['auto_mark_absent_buffer_minutes'];
        }
        if (array_key_exists('clock_in_reminder_enabled', $data)) {
            $hr['clock_in_reminder_enabled'] = (bool) $data['clock_in_reminder_enabled'];
        }
        if (isset($data['clock_in_reminder_minutes'])) {
            $hr['clock_in_reminder_minutes'] = (int) $data['clock_in_reminder_minutes'];
        }
        if (array_key_exists('include_national_holidays', $data)) {
            $hr['include_national_holidays'] = (bool) $data['include_national_holidays'];
        }
        if (isset($data['holidays']) && is_array($data['holidays'])) {
            $hr['holidays'] = app(HrHolidayService::class)->normalize($data['holidays']);
        }
        if (isset($data['annual_leave_days'])) {
            $hr['annual_leave_days'] = (int) $data['annual_leave_days'];
        }
        if (isset($data['max_permission_days'])) {
            $hr['max_permission_days'] = (int) $data['max_permission_days'];
        }
        if (isset($data['leave_carry_forward_max'])) {
            $hr['leave_carry_forward_max'] = (int) $data['leave_carry_forward_max'];
        }
        if (isset($data['leave_accrual_mode'])) {
            $hr['leave_accrual_mode'] = strtoupper((string) $data['leave_accrual_mode']);
        }

        $payrollInput = [];
        if (isset($data['payroll']) && is_array($data['payroll'])) {
            $payrollInput = $data['payroll'];
        } else {
            $payrollKeys = [
                'bpjs_employee_rate',
                'bpjs_employer_rate',
                'late_deduction_per_15min',
                'overtime_multiplier',
                'working_days_per_month',
                'use_ter',
                'absent_deduction_multiplier',
            ];
            $payrollInput = array_intersect_key($data, array_flip($payrollKeys));
        }
        if ($payrollInput !== []) {
            $hr['payroll'] = array_merge($hr['payroll'] ?? [], $payrollInput);
        }

        $captureKeys = ['require_gps', 'require_selfie', 'max_gps_accuracy_m'];
        $captureInput = array_intersect_key($data, array_flip($captureKeys));
        if ($captureInput !== []) {
            $hr['attendance_capture'] = array_merge($hr['attendance_capture'] ?? [], $captureInput);
        }

        $settings['hr'] = $hr;
        $company->update(['settings' => $settings]);

        return $this->resolve($user);
    }

    public function resolve(User $user): array
    {
        return $this->resolveForCompany($this->companyFor($user));
    }

    public function resolveForCompany(Company $company): array
    {
        $hr = $company->settings['hr'] ?? [];
        $envCapture = config('hr.attendance_capture', []);
        $holidayService = app(HrHolidayService::class);
        $nationalService = app(NationalHolidayService::class);
        $currentYear = (int) now()->year;

        return [
            'work_start' => (string) ($hr['work_start'] ?? config('hr.work_start', '08:00')),
            'work_end' => (string) ($hr['work_end'] ?? config('hr.work_end', '17:00')),
            'late_grace_minutes' => (int) ($hr['late_grace_minutes'] ?? config('hr.late_grace_minutes', 15)),
            'require_gps' => (bool) ($hr['attendance_capture']['require_gps'] ?? $envCapture['require_gps'] ?? true),
            'require_selfie' => (bool) ($hr['attendance_capture']['require_selfie'] ?? $envCapture['require_selfie'] ?? true),
            'max_gps_accuracy_m' => (int) ($hr['attendance_capture']['max_gps_accuracy_m'] ?? $envCapture['max_gps_accuracy_m'] ?? 80),
            'auto_mark_absent' => (bool) ($hr['auto_mark_absent'] ?? true),
            'auto_mark_absent_buffer_minutes' => (int) ($hr['auto_mark_absent_buffer_minutes'] ?? 30),
            'clock_in_reminder_enabled' => (bool) ($hr['clock_in_reminder_enabled'] ?? true),
            'clock_in_reminder_minutes' => (int) ($hr['clock_in_reminder_minutes'] ?? 15),
            'include_national_holidays' => (bool) ($hr['include_national_holidays'] ?? true),
            'holidays' => $holidayService->listCompanyHolidays($company),
            'national_holidays' => $holidayService->includesNational($company)
                ? $nationalService->forYear($currentYear)
                : [],
            'effective_holidays' => $holidayService->listMergedForCompany($company, $currentYear),
            'national_holiday_years' => $nationalService->availableYears(),
            'annual_leave_days' => (int) ($hr['annual_leave_days'] ?? config('hr.annual_leave_days', 12)),
            'max_permission_days' => (int) ($hr['max_permission_days'] ?? config('hr.max_permission_days', 1)),
            'leave_carry_forward_max' => (int) ($hr['leave_carry_forward_max'] ?? config('hr.leave_carry_forward_max', 6)),
            'leave_accrual_mode' => (string) ($hr['leave_accrual_mode'] ?? config('hr.leave_accrual_mode', 'ANNUAL')),
            'payroll' => $this->payrollConfigForCompany($company),
        ];
    }

    public function payrollConfigForCompany(Company $company): array
    {
        $payroll = $company->settings['hr']['payroll'] ?? [];
        $defaults = config('hr.payroll', []);

        return [
            'bpjs_employee_rate' => (float) ($payroll['bpjs_employee_rate'] ?? $defaults['bpjs_employee_rate'] ?? 0.02),
            'bpjs_employer_rate' => (float) ($payroll['bpjs_employer_rate'] ?? $defaults['bpjs_employer_rate'] ?? 0.0374),
            'late_deduction_per_15min' => (float) ($payroll['late_deduction_per_15min'] ?? $defaults['late_deduction_per_15min'] ?? 25000),
            'absent_deduction_multiplier' => (float) ($payroll['absent_deduction_multiplier'] ?? $defaults['absent_deduction_multiplier'] ?? 1.0),
            'overtime_multiplier' => (float) ($payroll['overtime_multiplier'] ?? $defaults['overtime_multiplier'] ?? 1.5),
            'use_ter' => (bool) ($payroll['use_ter'] ?? $defaults['use_ter'] ?? true),
            'working_days_per_month' => (int) ($payroll['working_days_per_month'] ?? config('hr.working_days_per_month', 22)),
        ];
    }

    public function attendanceCaptureFor(User $user): array
    {
        $resolved = $this->resolve($user);

        return array_merge(config('hr.attendance_capture', []), [
            'require_gps' => $resolved['require_gps'],
            'require_selfie' => $resolved['require_selfie'],
            'max_gps_accuracy_m' => $resolved['max_gps_accuracy_m'],
        ]);
    }

    protected function companyFor(User $user): Company
    {
        $company = Company::query()->find($user->default_company_id);

        if (! $company) {
            throw new ApiException('Perusahaan tidak ditemukan.', 404, 'COMPANY_NOT_FOUND');
        }

        return $company;
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