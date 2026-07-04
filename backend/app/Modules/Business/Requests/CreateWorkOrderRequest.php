<?php

namespace App\Modules\Business\Requests;

use App\Modules\Business\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class CreateWorkOrderRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeStrings(['title', 'description']);
    }

    public function rules(): array
    {
        return [
            'ticket_id' => ['nullable', 'integer', 'exists:cs_ops_tickets,id'],
            'account_id' => ['nullable', 'integer', 'exists:cs_crm_accounts,id'],
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:5000'],
            'scheduled_date' => ['nullable', 'date'],
        ];
    }
}