<?php

namespace App\Modules\Business\Requests;

use App\Modules\Business\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after_or_equal:clock_in_at'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'clear_capture' => ['sometimes', 'boolean'],
        ];
    }
}