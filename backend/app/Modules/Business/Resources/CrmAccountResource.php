<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'account_code' => $this->account_code,
            'name' => $this->name,
            'account_type' => $this->account_type,
            'status' => $this->status,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'npwp' => $this->npwp,
            'address' => $this->address,
            'city' => $this->city,
            'credit_limit' => (float) $this->credit_limit,
            'notes' => $this->notes,
            'contacts_count' => $this->whenCounted('contacts'),
            'contacts' => CrmContactResource::collection($this->whenLoaded('contacts')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}