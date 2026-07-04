<?php

namespace App\Modules\Business\Models;

use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveEntitlement extends Model
{
    use BelongsToCompany, BelongsToTenant;

    protected $table = 'cs_hr_leave_entitlements';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'employee_id',
        'year',
        'base_entitlement',
        'carried_forward',
        'adjustment',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'base_entitlement' => 'decimal:2',
            'carried_forward' => 'decimal:2',
            'adjustment' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}