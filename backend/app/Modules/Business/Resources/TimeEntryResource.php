<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'project_id' => $this->project_id,
            'employee_id' => $this->employee_id,
            'entry_date' => $this->entry_date?->format('Y-m-d'),
            'hours' => (float) $this->hours,
            'hourly_cost' => (float) $this->hourly_cost,
            'line_cost' => round((float) $this->hours * (float) $this->hourly_cost, 2),
            'is_billable' => $this->is_billable,
            'description' => $this->description,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}