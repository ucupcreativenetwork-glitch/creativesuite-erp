<?php

namespace App\Modules\Iam\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'cs_core_audit_logs';

    protected $fillable = [
        'tenant_id', 'company_id', 'event_type', 'entity_type', 'entity_id',
        'entity_public_id', 'actor_id', 'actor_email', 'old_values', 'new_values', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}