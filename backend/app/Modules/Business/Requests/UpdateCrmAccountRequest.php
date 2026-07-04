<?php

namespace App\Modules\Business\Requests;

use App\Modules\Business\Enums\AccountStatus;
use App\Modules\Business\Enums\AccountType;
use App\Modules\Business\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmAccountRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeStrings([
            'account_code', 'name', 'email', 'phone', 'whatsapp', 'npwp', 'address', 'city', 'notes',
        ]);
    }

    public function rules(): array
    {
        return [
            'account_code' => ['sometimes', 'string', 'max:30'],
            'name' => ['sometimes', 'string', 'max:300'],
            'account_type' => ['sometimes', Rule::enum(AccountType::class)],
            'status' => ['sometimes', Rule::enum(AccountStatus::class)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'npwp' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}