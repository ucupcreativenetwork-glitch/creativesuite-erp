<?php

namespace App\Modules\Platform\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_code' => ['sometimes', 'nullable', 'string', Rule::exists('cs_platform_subscription_plans', 'code')],
            'max_users' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'max_branches' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'max_storage_mb' => ['sometimes', 'integer', 'min:256', 'max:1048576'],
            'trial_ends_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}