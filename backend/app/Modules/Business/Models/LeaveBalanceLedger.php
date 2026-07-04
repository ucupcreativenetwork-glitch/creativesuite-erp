<?php

namespace App\Modules\Business\Models;

use App\Modules\Business\Enums\LeaveBalanceEntryType;
use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceLedger extends Model
{
    use BelongsToCompany, BelongsToTenant;

    public $timestamps = false;

    protected $table = 'cs_hr_leave_balance_ledger';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'employee_id',
        'year',
        'entry_type',
        'days',
        'leave_request_id',
        'notes',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entry_type' => LeaveBalanceEntryType::class,
            'days' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}