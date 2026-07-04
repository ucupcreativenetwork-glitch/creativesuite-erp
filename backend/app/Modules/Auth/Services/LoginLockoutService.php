<?php

namespace App\Modules\Auth\Services;

use App\Modules\Core\Models\User;
use App\Modules\Iam\Services\AuditLogService;
use App\Support\Exceptions\ApiException;

class LoginLockoutService
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function assertNotLocked(User $user): void
    {
        if ($user->locked_until && $user->locked_until->isFuture()) {
            $minutes = now()->diffInMinutes($user->locked_until) + 1;
            throw new ApiException(
                "Akun terkunci. Coba lagi dalam {$minutes} menit atau reset password.",
                423,
                'ACCOUNT_LOCKED',
            );
        }
    }

    public function recordFailedAttempt(User $user): void
    {
        $max = config('auth_activation.lockout.max_attempts', 5);
        $attempts = $user->failed_login_attempts + 1;

        $updates = ['failed_login_attempts' => $attempts];

        if ($attempts >= $max) {
            $updates['locked_until'] = now()->addMinutes(config('auth_activation.lockout.lockout_minutes', 15));
            $this->auditLog->record(
                $user,
                'ACCOUNT_LOCKED',
                'User',
                $user->id,
                $user->public_id,
                null,
                ['locked_until' => $updates['locked_until']->toIso8601String(), 'attempts' => $attempts],
                $user->default_company_id,
            );
        }

        $user->update($updates);
    }

    public function clearAttempts(User $user): void
    {
        if ($user->failed_login_attempts > 0 || $user->locked_until) {
            $user->update([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);
        }
    }
}