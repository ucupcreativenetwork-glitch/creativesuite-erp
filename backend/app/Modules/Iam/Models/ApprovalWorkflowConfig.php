<?php

namespace App\Modules\Iam\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflowConfig extends Model
{
    use BelongsToTenant;

    protected $table = 'cs_core_approval_workflow_configs';

    protected $fillable = [
        'tenant_id', 'company_id', 'public_id', 'name', 'module',
        'is_default', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class, 'workflow_config_id')->orderBy('step_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}