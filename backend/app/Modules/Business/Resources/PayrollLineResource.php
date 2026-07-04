<?php

namespace App\Modules\Business\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->employee_id,
            'gross_salary' => (float) $this->gross_salary,
            'allowance_amount' => (float) ($this->allowance_amount ?? 0),
            'pph21_amount' => (float) $this->pph21_amount,
            'bpjs_amount' => (float) $this->bpjs_amount,
            'attendance_deduction' => (float) ($this->attendance_deduction ?? 0),
            'overtime_amount' => (float) ($this->overtime_amount ?? 0),
            'other_deductions' => (float) $this->other_deductions,
            'net_salary' => (float) $this->net_salary,
            'total_deductions' => round(
                (float) $this->pph21_amount
                + (float) $this->bpjs_amount
                + (float) ($this->attendance_deduction ?? 0)
                + (float) $this->other_deductions,
                2,
            ),
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
        ];
    }
}