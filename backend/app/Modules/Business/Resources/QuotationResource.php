<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'quotation_number' => $this->quotation_number,
            'account_id' => $this->account_id,
            'customer_name' => $this->customer_name,
            'quotation_date' => $this->quotation_date?->format('Y-m-d'),
            'valid_until' => $this->valid_until?->format('Y-m-d'),
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'lines' => QuotationLineResource::collection($this->whenLoaded('lines')),
            'account' => new CrmAccountResource($this->whenLoaded('account')),
            'invoice_public_id' => $this->whenLoaded('invoice', fn () => $this->invoice?->public_id),
            'project_public_id' => $this->whenLoaded('project', fn () => $this->project?->public_id),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}