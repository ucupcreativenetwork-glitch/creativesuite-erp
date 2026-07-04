<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectBudgetSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'budget' => (float) $this['budget'],
            'actual_cost' => (float) $this['actual_cost'],
            'variance' => (float) $this['variance'],
            'utilization_pct' => (float) $this['utilization_pct'],
            'total_hours' => (float) $this['total_hours'],
            'billable_hours' => (float) $this['billable_hours'],
            'invoiced_milestones' => (float) $this['invoiced_milestones'],
            'pending_milestones' => (float) $this['pending_milestones'],
            'total_invoiced' => (float) ($this['total_invoiced'] ?? 0),
            'total_collected' => (float) ($this['total_collected'] ?? 0),
            'outstanding_ar' => (float) ($this['outstanding_ar'] ?? 0),
            'draft_invoices' => (float) ($this['draft_invoices'] ?? 0),
            'invoice_count' => (int) ($this['invoice_count'] ?? 0),
        ];
    }
}