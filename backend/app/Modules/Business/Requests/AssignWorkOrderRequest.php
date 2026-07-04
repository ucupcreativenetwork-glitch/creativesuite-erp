<?php

namespace App\Modules\Business\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technician_id' => ['required', 'integer', 'exists:cs_core_users,id'],
        ];
    }
}