<?php

namespace App\Modules\Iam\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowStep extends Model
{
    public $timestamps = false;

    protected $table = 'cs_core_approval_workflow_steps';

    protected $fillable = [
        'workflow_config_id', 'step_order', 'approver_role_code', 'can_override', 'sla_hours',
    ];

    protected function casts(): array
    {
        return ['can_override' => 'boolean'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowConfig::class, 'workflow_config_id');
    }
}