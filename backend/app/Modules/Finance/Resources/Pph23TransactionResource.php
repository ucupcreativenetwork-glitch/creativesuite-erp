<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Pph23TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
            'fiscal_year' => $this->fiscal_year,
            'fiscal_month' => $this->fiscal_month,
            'vendor_npwp' => $this->vendor_npwp,
            'vendor_name' => $this->vendor_name,
            'dpp_amount' => (float) $this->dpp_amount,
            'pph23_rate' => (float) $this->pph23_rate,
            'pph23_amount' => (float) $this->pph23_amount,
            'ebupot_document_id' => $this->ebupot_document_id,
        ];
    }
}