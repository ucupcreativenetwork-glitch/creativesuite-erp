<?php

namespace App\Modules\Business\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHrSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'work_start' => ['sometimes', 'date_format:H:i'],
            'work_end' => ['sometimes', 'date_format:H:i'],
            'late_grace_minutes' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'require_gps' => ['sometimes', 'boolean'],
            'require_selfie' => ['sometimes', 'boolean'],
            'max_gps_accuracy_m' => ['sometimes', 'integer', 'min:10', 'max:500'],
            'auto_mark_absent' => ['sometimes', 'boolean'],
            'auto_mark_absent_buffer_minutes' => ['sometimes', 'integer', 'min:0', 'max:180'],
            'clock_in_reminder_enabled' => ['sometimes', 'boolean'],
            'clock_in_reminder_minutes' => ['sometimes', 'integer', 'min:5', 'max:120'],
            'include_national_holidays' => ['sometimes', 'boolean'],
            'holidays' => ['sometimes', 'array', 'max:100'],
            'holidays.*.date' => ['required_with:holidays', 'date'],
            'holidays.*.name' => ['required_with:holidays', 'string', 'max:120'],
            'annual_leave_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'max_permission_days' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'leave_carry_forward_max' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'leave_accrual_mode' => ['sometimes', 'string', 'in:ANNUAL,MONTHLY'],
            'payroll' => ['sometimes', 'array'],
            'payroll.bpjs_employee_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'payroll.bpjs_employer_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'payroll.late_deduction_per_15min' => ['sometimes', 'numeric', 'min:0'],
            'payroll.overtime_multiplier' => ['sometimes', 'numeric', 'min:0'],
            'payroll.working_days_per_month' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'payroll.use_ter' => ['sometimes', 'boolean'],
            'payroll.absent_deduction_multiplier' => ['sometimes', 'numeric', 'min:0'],
            'bpjs_employee_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'bpjs_employer_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'late_deduction_per_15min' => ['sometimes', 'numeric', 'min:0'],
            'overtime_multiplier' => ['sometimes', 'numeric', 'min:0'],
            'working_days_per_month' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'use_ter' => ['sometimes', 'boolean'],
            'absent_deduction_multiplier' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}