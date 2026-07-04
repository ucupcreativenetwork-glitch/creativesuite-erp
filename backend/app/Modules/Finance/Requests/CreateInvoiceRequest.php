<?php

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\InvoiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_type' => ['required', Rule::enum(InvoiceType::class)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'counterparty_name' => ['required', 'string', 'max:300'],
            'counterparty_npwp' => ['nullable', 'string', 'max:20'],
            'counterparty_phone' => ['nullable', 'string', 'max:20'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_ppn_inclusive' => ['nullable', 'boolean'],
            'is_pph23_applicable' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'integer', 'exists:cs_core_branches,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.account_id' => ['nullable', 'integer', 'exists:cs_fin_chart_of_accounts,id'],
        ];
    }
}