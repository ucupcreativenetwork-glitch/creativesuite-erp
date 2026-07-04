<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'entry_number' => $this->entry_number,
            'entry_date' => $this->entry_date?->format('Y-m-d'),
            'journal_type' => $this->journal_type,
            'status' => $this->status,
            'description' => $this->description,
            'reference_no' => $this->reference_no,
            'total_debit' => (float) $this->total_debit,
            'total_credit' => (float) $this->total_credit,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'fiscal_period' => $this->whenLoaded('fiscalPeriod', fn () => [
                'year' => $this->fiscalPeriod->year,
                'month' => $this->fiscalPeriod->month,
                'name' => $this->fiscalPeriod->name,
            ]),
            'lines' => JournalEntryLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}