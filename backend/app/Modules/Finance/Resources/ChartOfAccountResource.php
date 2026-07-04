<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChartOfAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'account_type' => $this->account_type,
            'parent_id' => $this->parent_id,
            'normal_balance' => $this->normal_balance,
            'is_postable' => $this->is_postable,
            'is_active' => $this->is_active,
            'description' => $this->description,
        ];
    }
}