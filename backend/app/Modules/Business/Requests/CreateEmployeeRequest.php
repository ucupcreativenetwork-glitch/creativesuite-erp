<?php

namespace App\Modules\Business\Requests;

use App\Modules\Business\Enums\ContractType;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEmployeeRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeStrings([
            'employee_number', 'device_pin', 'full_name', 'email', 'phone', 'job_title', 'department', 'bpjs_number',
        ]);
    }

    public function rules(): array
    {
        return [
            'employee_number' => ['nullable', 'string', 'max:30'],
            'device_pin' => ['nullable', 'string', 'max:40'],
            'full_name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'allowance_amount' => ['nullable', 'numeric', 'min:0'],
            'ter_category' => ['nullable', 'string', Rule::in(['A', 'B', 'C'])],
            'bpjs_number' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::enum(EmployeeStatus::class)],
            'hire_date' => ['nullable', 'date'],
            'contract_type' => ['nullable', Rule::enum(ContractType::class)],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'user_id' => ['nullable', 'integer', 'exists:cs_core_users,id'],
        ];
    }
}