<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'employee_number' => $this->employee_number,
            'device_pin' => $this->device_pin,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'department' => $this->department,
            'base_salary' => (float) $this->base_salary,
            'allowance_amount' => (float) ($this->allowance_amount ?? 0),
            'ter_category' => $this->ter_category ?? 'A',
            'bpjs_number' => $this->bpjs_number,
            'status' => $this->status,
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'contract_type' => $this->contract_type?->value ?? $this->contract_type,
            'contract_start' => $this->contract_start?->format('Y-m-d'),
            'contract_end' => $this->contract_end?->format('Y-m-d'),
            'user_id' => $this->user_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}