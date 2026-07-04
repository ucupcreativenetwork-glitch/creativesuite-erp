<?php

namespace App\Modules\Platform\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformSubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'price_monthly' => (float) $this->price_monthly,
            'price_yearly' => (float) $this->price_yearly,
            'max_users' => $this->max_users,
            'max_branches' => $this->max_branches,
            'max_storage_mb' => $this->max_storage_mb,
            'features' => $this->features ?? [],
            'is_active' => (bool) $this->is_active,
        ];
    }
}