<?php

namespace App\Modules\Iam\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'step_order' => $this->step_order,
            'action' => $this->action?->value ?? $this->action,
            'actor_role_code' => $this->actor_role_code,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'actor' => $this->whenLoaded('actor', fn () => [
                'id' => $this->actor->public_id,
                'full_name' => $this->actor->full_name,
            ]),
        ];
    }
}