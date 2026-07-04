<?php

namespace App\Modules\Integration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExternalCreatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['nullable', 'integer', 'exists:cs_crm_accounts,id'],
            'vendor_name' => ['required', 'string', 'max:200'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'integer', 'exists:cs_inv_items,id'],
            'lines.*.description' => ['required', 'string', 'max:300'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}