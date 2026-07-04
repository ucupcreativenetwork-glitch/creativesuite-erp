<?php

namespace App\Modules\Integration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectorConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'connector_type' => $this->connector_type?->value ?? $this->connector_type,
            'employee_match_field' => $this->employee_match_field,
            'settings' => $this->settings,
            'is_active' => (bool) $this->is_active,
            'last_ingest_at' => $this->last_ingest_at?->toIso8601String(),
            'last_processed_count' => (int) ($this->last_processed_count ?? 0),
            'last_error_count' => (int) ($this->last_error_count ?? 0),
            'push_url' => url('/api/v1/external/connectors/push'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}