<?php

namespace App\Modules\Integration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookEndpointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'url' => $this->url,
            'events' => $this->events ?? [],
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}