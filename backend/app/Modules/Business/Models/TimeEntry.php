<?php

namespace App\Modules\Business\Models;

use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeEntry extends Model
{
    use BelongsToCompany, BelongsToTenant, SoftDeletes;

    protected $table = 'cs_prj_time_entries';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'public_id',
        'project_id',
        'employee_id',
        'user_id',
        'entry_date',
        'hours',
        'hourly_cost',
        'is_billable',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'hours' => 'decimal:2',
            'hourly_cost' => 'decimal:2',
            'is_billable' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}