<?php

namespace App\Modules\Iam\Models;

use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IamPushDevice extends Model
{
    use BelongsToTenant;

    protected $table = 'cs_core_push_devices';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'expo_push_token',
        'platform',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}