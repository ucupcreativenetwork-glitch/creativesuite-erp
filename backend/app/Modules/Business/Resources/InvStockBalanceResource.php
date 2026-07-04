<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvStockBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'item_id' => $this->item_id,
            'warehouse_id' => $this->warehouse_id,
            'quantity_on_hand' => (float) $this->quantity_on_hand,
            'item' => new InvItemResource($this->whenLoaded('item')),
            'warehouse' => new InvWarehouseResource($this->whenLoaded('warehouse')),
        ];
    }
}