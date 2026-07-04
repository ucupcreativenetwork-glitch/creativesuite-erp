<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = auth('api')->user()?->tenant_id;

        return [
            'full_name' => ['required', 'string', 'max:200'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('cs_core_users', 'email')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_code' => ['nullable', 'string', 'max:50'],
            'company_id' => ['nullable', 'integer', 'exists:cs_core_companies,id'],
            'branch_id' => ['nullable', 'integer', 'exists:cs_core_branches,id'],
        ];
    }
}