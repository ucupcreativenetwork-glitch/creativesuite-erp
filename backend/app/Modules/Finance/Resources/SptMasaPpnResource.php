<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SptMasaPpnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'month' => $this->month,
            'status' => $this->status,
            'total_pk' => (float) $this->total_pk,
            'total_pm' => (float) $this->total_pm,
            'kurang_lebih_bayar' => (float) $this->kurang_lebih_bayar,
            'data_json' => $this->data_json,
            'finalized_at' => $this->finalized_at?->toIso8601String(),
        ];
    }
}