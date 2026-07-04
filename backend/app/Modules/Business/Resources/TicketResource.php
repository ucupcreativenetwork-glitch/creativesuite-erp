<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'ticket_number' => $this->ticket_number,
            'account_id' => $this->account_id,
            'subject' => $this->subject,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'public_id' => $this->assignee->public_id,
                'full_name' => $this->assignee->full_name,
            ]),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'account' => new CrmAccountResource($this->whenLoaded('account')),
            'work_orders' => WorkOrderResource::collection($this->whenLoaded('workOrders')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}