<?php

namespace App\Modules\Business\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'nullable', 'integer', 'exists:cs_hr_employees,id'],
            'entry_date' => ['sometimes', 'date'],
            'hours' => ['sometimes', 'numeric', 'min:0.25', 'max:24'],
            'hourly_cost' => ['sometimes', 'numeric', 'min:0'],
            'is_billable' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}