<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'status' => $this->status?->value ?? $this->status,
            'sort_order' => $this->sort_order,
            'invoice_public_id' => $this->whenLoaded('invoice', fn () => $this->invoice?->public_id),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}