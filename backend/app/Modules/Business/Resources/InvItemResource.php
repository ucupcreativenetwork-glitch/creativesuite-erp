<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'uom' => $this->uom,
            'unit_cost' => (float) $this->unit_cost,
            'reorder_level' => (float) $this->reorder_level,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}