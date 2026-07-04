<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvStockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'movement_number' => $this->movement_number,
            'item_id' => $this->item_id,
            'warehouse_id' => $this->warehouse_id,
            'movement_type' => $this->movement_type,
            'quantity' => (float) $this->quantity,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'notes' => $this->notes,
            'item' => new InvItemResource($this->whenLoaded('item')),
            'warehouse' => new InvWarehouseResource($this->whenLoaded('warehouse')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}