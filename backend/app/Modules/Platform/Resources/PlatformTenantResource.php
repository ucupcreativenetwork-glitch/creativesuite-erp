<?php

namespace App\Modules\Platform\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformTenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        $plan = $this->relationLoaded('plan') ? $this->plan : null;

        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status?->value ?? $this->status,
            'plan' => $plan ? [
                'code' => $plan->code,
                'name' => $plan->name,
            ] : null,
            'max_users' => $this->max_users,
            'max_branches' => $this->max_branches,
            'max_storage_mb' => $this->max_storage_mb,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user_count' => $this->when(isset($this->user_count), (int) $this->user_count),
            'company_count' => $this->when(isset($this->company_count), (int) $this->company_count),
        ];
    }
}