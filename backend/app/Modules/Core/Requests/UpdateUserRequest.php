<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('full_name')) {
            $this->merge(['full_name' => trim(strip_tags($this->input('full_name')))]);
        }

        if ($this->has('phone')) {
            $this->merge(['phone' => trim(strip_tags($this->input('phone')))]);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}