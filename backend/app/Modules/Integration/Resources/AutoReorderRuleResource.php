<?php

namespace App\Modules\Integration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutoReorderRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'vendor_id' => $this->vendor_id,
            'vendor_name' => $this->vendor_name,
            'warehouse_id' => $this->warehouse_id,
            'item_public_ids' => $this->item_public_ids,
            'order_multiplier' => (float) $this->order_multiplier,
            'auto_submit' => (bool) $this->auto_submit,
            'auto_approve' => (bool) $this->auto_approve,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}