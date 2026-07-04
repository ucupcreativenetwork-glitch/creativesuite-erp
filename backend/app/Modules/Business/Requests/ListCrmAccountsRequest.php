<?php

namespace App\Modules\Business\Requests;

use App\Modules\Business\Enums\AccountStatus;
use App\Modules\Business\Enums\AccountType;
use App\Modules\Business\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCrmAccountsRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeStrings(['search']);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::enum(AccountStatus::class)],
            'account_type' => ['nullable', Rule::enum(AccountType::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}