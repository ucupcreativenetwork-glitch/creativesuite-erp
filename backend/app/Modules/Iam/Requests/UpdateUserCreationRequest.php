<?php

namespace App\Modules\Iam\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserCreationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'max:200'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'branch_id' => ['nullable', 'integer', 'exists:cs_core_branches,id'],
            'requested_role_id' => ['sometimes', 'integer', 'exists:cs_core_roles,id'],
            'position' => ['nullable', 'string', 'max:150'],
            'direct_manager_id' => ['nullable', 'integer', 'exists:cs_core_users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'submit' => ['sometimes', 'boolean'],
        ];
    }
}