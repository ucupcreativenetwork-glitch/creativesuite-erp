<?php

namespace App\Modules\Auth\Models;

use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivationToken extends Model
{
    use BelongsToTenant;

    protected $table = 'cs_core_user_activation_tokens';

    protected $fillable = [
        'tenant_id', 'user_id', 'token', 'expired_at', 'used_at', 'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'expired_at' => 'datetime',
            'used_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expired_at->isFuture();
    }
}