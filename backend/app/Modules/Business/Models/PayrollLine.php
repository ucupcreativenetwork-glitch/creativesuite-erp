<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLine extends Model
{
    protected $table = 'cs_hr_payroll_lines';

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'gross_salary',
        'allowance_amount',
        'pph21_amount',
        'bpjs_amount',
        'attendance_deduction',
        'overtime_amount',
        'other_deductions',
        'net_salary',
    ];

    protected function casts(): array
    {
        return [
            'gross_salary' => 'decimal:2',
            'allowance_amount' => 'decimal:2',
            'pph21_amount' => 'decimal:2',
            'bpjs_amount' => 'decimal:2',
            'attendance_deduction' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}