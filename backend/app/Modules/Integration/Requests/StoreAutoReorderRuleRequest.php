<?php

namespace App\Modules\Integration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAutoReorderRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'vendor_id' => ['nullable', 'integer', 'exists:cs_crm_accounts,id'],
            'vendor_name' => ['required', 'string', 'max:200'],
            'warehouse_id' => ['required', 'integer', 'exists:cs_inv_warehouses,id'],
            'item_public_ids' => ['nullable', 'array'],
            'item_public_ids.*' => ['string', 'size:36'],
            'order_multiplier' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'auto_submit' => ['sometimes', 'boolean'],
            'auto_approve' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}