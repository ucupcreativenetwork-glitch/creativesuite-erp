<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankStatementLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'bank_account_id' => $this->bank_account_id,
            'bank_account_code' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount?->code),
            'bank_account_name' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount?->name),
            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
            'description' => $this->description,
            'reference_no' => $this->reference_no,
            'debit' => (float) $this->debit,
            'credit' => (float) $this->credit,
            'status' => $this->status,
            'matched_payment' => new PaymentResource($this->whenLoaded('matchedPayment')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}