<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EfakturDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'nomor_faktur' => $this->nomor_faktur,
            'status' => $this->status,
            'buyer_npwp' => $this->buyer_npwp,
            'buyer_name' => $this->buyer_name,
            'dpp' => (float) $this->dpp,
            'ppn' => (float) $this->ppn,
            'total' => (float) $this->total,
            'tanggal_faktur' => $this->tanggal_faktur?->format('Y-m-d'),
            'djp_reference' => $this->djp_reference,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
        ];
    }
}