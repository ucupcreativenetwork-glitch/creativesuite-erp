<?php

namespace App\Modules\Business\Models;

use App\Modules\Business\Enums\MilestoneStatus;
use App\Modules\Finance\Models\Invoice;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Milestone extends Model
{
    use BelongsToCompany, BelongsToTenant, SoftDeletes;

    protected $table = 'cs_prj_milestones';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'public_id',
        'project_id',
        'name',
        'description',
        'amount',
        'due_date',
        'status',
        'sort_order',
        'invoice_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'status' => MilestoneStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}