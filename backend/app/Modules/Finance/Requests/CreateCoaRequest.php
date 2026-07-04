<?php

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\AccountCategory;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Enums\NormalBalance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCoaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(AccountCategory::class)],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'parent_id' => ['nullable', 'integer', 'exists:cs_fin_chart_of_accounts,id'],
            'normal_balance' => ['required', Rule::enum(NormalBalance::class)],
            'is_postable' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}