<?php

namespace App\Modules\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'internal_id' => $this->id,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'is_active' => $this->is_active,
            'is_platform_admin' => (bool) $this->is_platform_admin,
            'account_status' => $this->account_status,
            'must_change_password' => $this->must_change_password,
            'provisioning_source' => $this->provisioning_source,
            'mfa_enabled' => $this->mfa_enabled,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'default_company' => new CompanyResource($this->whenLoaded('defaultCompany')),
            'default_branch' => new BranchResource($this->whenLoaded('defaultBranch')),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'companies' => CompanyResource::collection($this->whenLoaded('companies')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}