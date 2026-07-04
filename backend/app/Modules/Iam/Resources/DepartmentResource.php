<?php

namespace App\Modules\Iam\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'allowed_roles' => $this->whenLoaded('allowedRoles', fn () => $this->allowedRoles->map(fn ($r) => [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
            ])),
        ];
    }
}