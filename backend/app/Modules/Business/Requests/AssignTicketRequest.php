<?php

namespace App\Modules\Business\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'integer', 'exists:cs_core_users,id'],
        ];
    }
}