<?php

namespace App\Modules\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status?->value,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
        ];
    }
}