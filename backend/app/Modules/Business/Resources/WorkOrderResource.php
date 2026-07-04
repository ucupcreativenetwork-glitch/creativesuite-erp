<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'work_order_number' => $this->work_order_number,
            'ticket_id' => $this->ticket_id,
            'account_id' => $this->account_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'scheduled_date' => $this->scheduled_date?->format('Y-m-d'),
            'technician_id' => $this->technician_id,
            'technician' => $this->whenLoaded('technician', fn () => [
                'public_id' => $this->technician->public_id,
                'full_name' => $this->technician->full_name,
            ]),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'ticket' => new TicketResource($this->whenLoaded('ticket')),
            'account' => new CrmAccountResource($this->whenLoaded('account')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}