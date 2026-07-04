<?php

namespace App\Modules\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'mfa_required' => $this->resource['mfa_required'] ?? false,
            'mfa_token' => $this->resource['mfa_token'] ?? null,
            'access_token' => $this->resource['access_token'] ?? null,
            'token_type' => $this->resource['token_type'] ?? 'bearer',
            'expires_in' => $this->resource['expires_in'] ?? null,
            'user' => new UserResource($this->resource['user'] ?? null),
            'tenant' => isset($this->resource['tenant'])
                ? new TenantResource($this->resource['tenant'])
                : null,
            'company' => isset($this->resource['company'])
                ? new CompanyResource($this->resource['company'])
                : null,
            'branch' => isset($this->resource['branch'])
                ? new BranchResource($this->resource['branch'])
                : null,
        ];
    }
}