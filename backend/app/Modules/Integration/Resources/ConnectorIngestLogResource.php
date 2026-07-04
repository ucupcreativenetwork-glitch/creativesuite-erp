<?php

namespace App\Modules\Integration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectorIngestLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'processed' => (int) $this->processed,
            'errors' => $this->errors ?? [],
            'error_count' => count($this->errors ?? []),
            'payload_hash' => $this->payload_hash,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}