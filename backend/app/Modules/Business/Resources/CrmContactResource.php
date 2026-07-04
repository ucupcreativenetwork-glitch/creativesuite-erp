<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'full_name' => $this->full_name,
            'job_title' => $this->job_title,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}