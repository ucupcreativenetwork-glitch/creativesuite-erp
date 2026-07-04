<?php

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_type' => ['required', Rule::enum(PaymentType::class)],
            'payment_date' => ['required', 'date'],
            'invoice_id' => ['nullable', 'integer', 'exists:cs_fin_invoices,id'],
            'counterparty_name' => ['nullable', 'string', 'max:300'],
            'counterparty_npwp' => ['nullable', 'string', 'max:20'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank_account_id' => ['required', 'integer', 'exists:cs_fin_chart_of_accounts,id'],
            'apply_pph23' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'integer', 'exists:cs_core_branches,id'],
        ];
    }
}