<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'request_number' => $this->request_number,
            'leave_type' => $this->leave_type?->value ?? $this->leave_type,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'total_days' => (int) $this->total_days,
            'reason' => $this->reason,
            'status' => $this->status?->value ?? $this->status,
            'rejection_reason' => $this->rejection_reason,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'employee' => $this->whenLoaded('employee', fn () => [
                'public_id' => $this->employee->public_id,
                'employee_number' => $this->employee->employee_number,
                'full_name' => $this->employee->full_name,
                'department' => $this->employee->department,
                'job_title' => $this->employee->job_title,
            ]),
            'requester' => $this->whenLoaded('requester', fn () => [
                'full_name' => $this->requester->full_name,
            ]),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'full_name' => $this->approver->full_name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}