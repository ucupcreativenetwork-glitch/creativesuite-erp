<?php

namespace App\Modules\Business\Requests;

use App\Modules\Business\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuotationRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeStrings(['customer_name', 'notes']);
    }

    public function rules(): array
    {
        return [
            'account_id' => ['nullable', 'integer', 'exists:cs_crm_accounts,id'],
            'customer_name' => ['sometimes', 'string', 'max:300'],
            'quotation_date' => ['sometimes', 'date'],
            'valid_until' => ['nullable', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:500'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0'],
        ];
    }
}