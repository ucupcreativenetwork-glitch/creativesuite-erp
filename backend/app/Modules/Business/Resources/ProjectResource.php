<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'project_number' => $this->project_number,
            'name' => $this->name,
            'account_id' => $this->account_id,
            'quotation_id' => $this->quotation_id,
            'status' => $this->status?->value ?? $this->status,
            'budget' => (float) $this->budget,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'account' => new CrmAccountResource($this->whenLoaded('account')),
            'quotation' => new QuotationResource($this->whenLoaded('quotation')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}