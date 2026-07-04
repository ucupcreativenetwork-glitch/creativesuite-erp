<?php

namespace App\Modules\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'entity_type' => $this->entity_type?->value,
            'npwp' => $this->npwp,
            'nitku' => $this->nitku,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'logo_url' => $this->resolvedLogoUrl(),
            'is_pkp' => $this->is_pkp,
            'is_active' => $this->is_active,
            'settings' => $this->settings ?? [],
        ];
    }
}