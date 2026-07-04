<?php

namespace App\Modules\Business\Requests;

use App\Modules\Business\Enums\TicketPriority;
use App\Modules\Business\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeStrings(['subject', 'description']);
    }

    public function rules(): array
    {
        return [
            'account_id' => ['nullable', 'integer', 'exists:cs_crm_accounts,id'],
            'subject' => ['sometimes', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
        ];
    }
}