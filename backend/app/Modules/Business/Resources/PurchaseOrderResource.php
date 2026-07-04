<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'po_number' => $this->po_number,
            'vendor_id' => $this->vendor_id,
            'vendor_name' => $this->vendor_name,
            'order_date' => $this->order_date?->format('Y-m-d'),
            'expected_date' => $this->expected_date?->format('Y-m-d'),
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'lines' => PurchaseOrderLineResource::collection($this->whenLoaded('lines')),
            'vendor' => new CrmAccountResource($this->whenLoaded('vendor')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}