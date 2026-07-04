<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employerRate = (float) (config('hr.payroll.bpjs_employer_rate') ?? 0.0374);
        $bpjsEmployerTotal = $this->relationLoaded('lines')
            ? round($this->lines->sum(fn ($line) => (float) $line->gross_salary * $employerRate), 2)
            : null;

        return [
            'public_id' => $this->public_id,
            'run_number' => $this->run_number,
            'period_year' => $this->period_year,
            'period_month' => $this->period_month,
            'status' => $this->status,
            'total_gross' => (float) $this->total_gross,
            'total_deductions' => (float) $this->total_deductions,
            'total_net' => (float) $this->total_net,
            'bpjs_employer_total' => $bpjsEmployerTotal,
            'journal_entry_public_id' => $this->whenLoaded('journalEntry', fn () => $this->journalEntry?->public_id),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'lines' => PayrollLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}