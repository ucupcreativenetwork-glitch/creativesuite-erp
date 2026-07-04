<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'legal_name' => ['sometimes', 'string', 'max:300'],
            'trade_name' => ['nullable', 'string', 'max:300'],
            'npwp' => ['nullable', 'string', 'max:20'],
            'nitku' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_pkp' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
            'settings.documents' => ['sometimes', 'array'],
            'settings.documents.invoice_types' => ['sometimes', 'array'],
            'settings.documents.invoice_types.SALES' => ['sometimes', 'array'],
            'settings.documents.invoice_types.SALES.label' => ['nullable', 'string', 'max:120'],
            'settings.documents.invoice_types.SALES.short_label' => ['nullable', 'string', 'max:60'],
            'settings.documents.invoice_types.SALES.document_title' => ['nullable', 'string', 'max:120'],
            'settings.documents.invoice_types.SALES.document_subtitle' => ['nullable', 'string', 'max:120'],
            'settings.documents.invoice_types.SALES.counterparty_label' => ['nullable', 'string', 'max:120'],
            'settings.documents.invoice_types.SALES.total_label' => ['nullable', 'string', 'max:120'],
            'settings.documents.invoice_types.SALES.meta_type_label' => ['nullable', 'string', 'max:120'],
            'settings.documents.invoice_types.PURCHASE' => ['sometimes', 'array'],
            'settings.documents.invoice_types.PURCHASE.label' => ['nullable', 'string', 'max:120'],
            'settings.documents.invoice_types.PURCHASE.short_label' => ['nullable', 'string', 'max:60'],
            'settings.documents.invoice_types.PURCHASE.document_title' => ['nullable', 'string', 'max:120'],
            'settings.documents.invoice_types.PURCHASE.document_subtitle' => ['nullable', 'string', 'max:120'],
            'settings.documents.invoice_types.PURCHASE.counterparty_label' => ['nullable', 'string', 'max:120'],
            'settings.documents.invoice_types.PURCHASE.total_label' => ['nullable', 'string', 'max:120'],
            'settings.documents.invoice_types.PURCHASE.meta_type_label' => ['nullable', 'string', 'max:120'],
        ];
    }
}