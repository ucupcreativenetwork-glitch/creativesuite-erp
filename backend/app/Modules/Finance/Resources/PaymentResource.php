<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'payment_number' => $this->payment_number,
            'payment_type' => $this->payment_type,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'invoice_id' => $this->invoice_id,
            'counterparty_name' => $this->counterparty_name,
            'counterparty_npwp' => $this->counterparty_npwp,
            'amount' => (float) $this->amount,
            'pph23_amount' => (float) $this->pph23_amount,
            'net_amount' => (float) $this->net_amount,
            'bank_account_id' => $this->bank_account_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'invoice' => $this->whenLoaded('invoice', fn () => [
                'id' => $this->invoice->id,
                'public_id' => $this->invoice->public_id,
                'invoice_number' => $this->invoice->invoice_number,
                'counterparty_name' => $this->invoice->counterparty_name,
                'total_amount' => (float) $this->invoice->total_amount,
                'paid_amount' => (float) $this->invoice->paid_amount,
            ]),
            'journal_entry' => new JournalEntryResource($this->whenLoaded('journalEntry')),
        ];
    }
}