<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'invoice_number' => $this->invoice_number,
            'invoice_type' => $this->invoice_type,
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'counterparty_name' => $this->counterparty_name,
            'counterparty_npwp' => $this->counterparty_npwp,
            'counterparty_phone' => $this->counterparty_phone,
            'status' => $this->status,
            'notes' => $this->notes,
            'subtotal' => (float) $this->subtotal,
            'dpp_amount' => (float) $this->dpp_amount,
            'ppn_rate' => (float) $this->ppn_rate,
            'ppn_amount' => (float) $this->ppn_amount,
            'pph23_amount' => (float) $this->pph23_amount,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'is_ppn_inclusive' => $this->is_ppn_inclusive,
            'lines' => InvoiceLineResource::collection($this->whenLoaded('lines')),
            'journal_entry' => new JournalEntryResource($this->whenLoaded('journalEntry')),
        ];
    }
}