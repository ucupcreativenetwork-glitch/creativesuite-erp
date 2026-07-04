<?php

namespace App\Modules\Iam\Models;

use App\Modules\Core\Models\User;
use App\Modules\Iam\Enums\ApprovalAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalHistory extends Model
{
    public $timestamps = false;

    protected $table = 'cs_core_approval_history';

    protected $fillable = [
        'request_id', 'step_order', 'action', 'actor_id', 'actor_role_code',
        'notes', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['action' => ApprovalAction::class, 'created_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(UserCreationRequest::class, 'request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}