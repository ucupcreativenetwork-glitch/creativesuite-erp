<?php

namespace App\Modules\Business\Requests;

use App\Modules\Business\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeStrings(['vendor_name', 'notes']);
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['nullable', 'integer', 'exists:cs_crm_accounts,id'],
            'vendor_name' => ['sometimes', 'string', 'max:300'],
            'order_date' => ['sometimes', 'date'],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'integer', 'exists:cs_inv_items,id'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:500'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0'],
        ];
    }
}