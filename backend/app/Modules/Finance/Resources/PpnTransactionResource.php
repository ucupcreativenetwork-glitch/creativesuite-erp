<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PpnTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_type' => $this->transaction_type,
            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
            'fiscal_year' => $this->fiscal_year,
            'fiscal_month' => $this->fiscal_month,
            'dpp_amount' => (float) $this->dpp_amount,
            'ppn_rate' => (float) $this->ppn_rate,
            'ppn_amount' => (float) $this->ppn_amount,
            'counterparty_name' => $this->counterparty_name,
            'counterparty_npwp' => $this->counterparty_npwp,
            'efaktur_document_id' => $this->efaktur_document_id,
        ];
    }
}