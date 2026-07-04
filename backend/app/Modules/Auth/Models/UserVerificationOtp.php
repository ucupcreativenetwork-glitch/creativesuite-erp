<?php

namespace App\Modules\Auth\Models;

use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVerificationOtp extends Model
{
    use BelongsToTenant;

    protected $table = 'cs_core_user_verification_otps';

    protected $fillable = [
        'tenant_id', 'user_id', 'otp_code', 'session_token',
        'expired_at', 'verified_at', 'attempt_count',
    ];

    protected function casts(): array
    {
        return [
            'expired_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->verified_at === null
            && $this->expired_at->isFuture()
            && $this->attempt_count < config('auth_activation.otp.max_attempts', 5);
    }
}