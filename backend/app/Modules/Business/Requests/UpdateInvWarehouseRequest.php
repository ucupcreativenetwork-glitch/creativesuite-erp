<?php

namespace App\Modules\Business\Requests;

use App\Modules\Business\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvWarehouseRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeStrings(['code', 'name']);
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:20'],
            'name' => ['sometimes', 'string', 'max:200'],
            'branch_id' => ['nullable', 'integer', 'exists:cs_core_branches,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}