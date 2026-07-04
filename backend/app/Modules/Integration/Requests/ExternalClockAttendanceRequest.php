<?php

namespace App\Modules\Integration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExternalClockAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_number' => ['nullable', 'string', 'max:30', 'required_without:employee_public_id'],
            'employee_public_id' => ['nullable', 'string', 'size:36', 'required_without:employee_number'],
            'pin' => ['nullable', 'string', 'max:30'],
            'attendance_date' => ['nullable', 'date'],
            'timestamp' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:PRESENT,LATE'],
            'notes' => ['nullable', 'string', 'max:500'],
            'external_ref' => ['nullable', 'string', 'max:120'],
        ];
    }
}