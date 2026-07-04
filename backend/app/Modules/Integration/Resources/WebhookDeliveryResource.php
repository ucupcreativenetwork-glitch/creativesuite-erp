<?php

namespace App\Modules\Integration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'payload' => $this->payload,
            'response_status' => $this->response_status,
            'response_body' => $this->response_body,
            'attempts' => (int) $this->attempts,
            'status' => $this->status,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'next_retry_at' => $this->next_retry_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}