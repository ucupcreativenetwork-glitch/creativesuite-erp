<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EbupotDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'nomor_bupot' => $this->nomor_bupot,
            'status' => $this->status,
            'vendor_npwp' => $this->vendor_npwp,
            'vendor_name' => $this->vendor_name,
            'dpp' => (float) $this->dpp,
            'pph23' => (float) $this->pph23,
            'tanggal_bupot' => $this->tanggal_bupot?->format('Y-m-d'),
            'djp_reference' => $this->djp_reference,
            'issued_at' => $this->issued_at?->toIso8601String(),
        ];
    }
}