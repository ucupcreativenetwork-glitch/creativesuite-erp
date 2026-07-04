<?php

namespace App\Modules\Business\Models;

use App\Modules\Business\Enums\ContractType;
use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use BelongsToCompany, BelongsToTenant, SoftDeletes;

    protected $table = 'cs_hr_employees';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'public_id',
        'employee_number',
        'device_pin',
        'full_name',
        'email',
        'phone',
        'job_title',
        'department',
        'base_salary',
        'allowance_amount',
        'ter_category',
        'bpjs_number',
        'status',
        'hire_date',
        'contract_type',
        'contract_start',
        'contract_end',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'allowance_amount' => 'decimal:2',
            'status' => EmployeeStatus::class,
            'hire_date' => 'date',
            'contract_type' => ContractType::class,
            'contract_start' => 'date',
            'contract_end' => 'date',
            'bpjs_number' => 'encrypted',
            'phone' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}