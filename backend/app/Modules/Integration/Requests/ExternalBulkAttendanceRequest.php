<?php

namespace App\Modules\Integration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExternalBulkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['nullable', 'string', 'max:40'],
            'records' => ['required', 'array', 'min:1', 'max:500'],
            'records.*.employee_number' => ['nullable', 'string', 'max:30'],
            'records.*.employee_public_id' => ['nullable', 'string', 'size:36'],
            'records.*.pin' => ['nullable', 'string', 'max:30'],
            'records.*.attendance_date' => ['required', 'date'],
            'records.*.clock_in_at' => ['nullable', 'date'],
            'records.*.clock_out_at' => ['nullable', 'date'],
            'records.*.status' => ['nullable', 'string', 'in:PRESENT,LATE,ABSENT,LEAVE'],
            'records.*.notes' => ['nullable', 'string', 'max:500'],
            'records.*.external_ref' => ['nullable', 'string', 'max:120'],
            'records.*.skip_if_exists' => ['nullable', 'boolean'],
        ];
    }
}