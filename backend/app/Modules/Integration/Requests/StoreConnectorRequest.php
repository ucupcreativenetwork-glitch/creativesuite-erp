<?php

namespace App\Modules\Integration\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConnectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'connector_type' => ['required', 'string', Rule::in(array_keys(config('integration.connector_types', [])))],
            'employee_match_field' => ['sometimes', 'string', Rule::in(array_keys(config('integration.connector_match_fields', [])))],
            'settings' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}