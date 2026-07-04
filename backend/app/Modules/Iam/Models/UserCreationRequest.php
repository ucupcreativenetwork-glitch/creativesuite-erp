<?php

namespace App\Modules\Iam\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Iam\Enums\UserRequestStatus;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserCreationRequest extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'cs_core_user_creation_requests';

    protected $fillable = [
        'public_id', 'request_number', 'tenant_id', 'company_id', 'branch_id', 'department_id',
        'requested_by', 'requested_role_id', 'full_name', 'email', 'phone', 'position',
        'direct_manager_id', 'notes', 'status', 'current_approval_level', 'workflow_config_id',
        'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason',
        'revision_notes', 'created_user_id', 'submitted_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserRequestStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function requestedRole(): BelongsTo { return $this->belongsTo(Role::class, 'requested_role_id'); }
    public function directManager(): BelongsTo { return $this->belongsTo(User::class, 'direct_manager_id'); }
    public function workflow(): BelongsTo { return $this->belongsTo(ApprovalWorkflowConfig::class, 'workflow_config_id'); }
    public function createdUser(): BelongsTo { return $this->belongsTo(User::class, 'created_user_id'); }
    public function history(): HasMany { return $this->hasMany(ApprovalHistory::class, 'request_id')->orderBy('created_at'); }
}