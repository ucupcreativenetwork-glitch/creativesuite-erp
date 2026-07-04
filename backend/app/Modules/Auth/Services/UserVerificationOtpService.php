<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\UserVerificationOtp;
use App\Modules\Auth\Notifications\OtpVerificationNotification;
use App\Modules\Core\Models\User;
use App\Support\Exceptions\ApiException;
use App\Support\Security\SensitiveData;
use Illuminate\Support\Str;

class UserVerificationOtpService
{
    public function generateAndSend(User $user): array
    {
        $sessionToken = Str::random(64);
        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        UserVerificationOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->update(['verified_at' => now()]);

        UserVerificationOtp::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'otp_code' => SensitiveData::hash($otpCode),
            'session_token' => SensitiveData::digest($sessionToken),
            'expired_at' => now()->addMinutes(config('auth_activation.otp_expire_minutes', 5)),
            'attempt_count' => 0,
        ]);

        $user->notify(new OtpVerificationNotification($otpCode));

        return [
            'session_token' => $sessionToken,
        ];
    }

    public function verify(User $user, string $sessionToken, string $otp): void
    {
        $record = UserVerificationOtp::query()
            ->where('user_id', $user->id)
            ->where('session_token', SensitiveData::digest($sessionToken))
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $record) {
            throw new ApiException('Sesi OTP tidak valid.', 400, 'INVALID_OTP_SESSION');
        }

        if ($record->expired_at->isPast()) {
            throw new ApiException('OTP sudah kadaluarsa. Minta OTP baru.', 400, 'OTP_EXPIRED');
        }

        if ($record->attempt_count >= config('auth_activation.otp.max_attempts', 5)) {
            throw new ApiException('Percobaan OTP melebihi batas. Minta OTP baru.', 429, 'OTP_MAX_ATTEMPTS');
        }

        if (! SensitiveData::verifyHash($otp, $record->otp_code)) {
            $record->increment('attempt_count');
            $remaining = config('auth_activation.otp.max_attempts', 5) - $record->attempt_count;
            throw new ApiException(
                "OTP tidak valid. Sisa percobaan: {$remaining}.",
                422,
                'INVALID_OTP',
            );
        }

        $record->update(['verified_at' => now()]);
    }

    public function resend(User $user, ?string $oldSessionToken = null): array
    {
        if ($oldSessionToken) {
            UserVerificationOtp::query()
                ->where('user_id', $user->id)
                ->where('session_token', SensitiveData::digest($oldSessionToken))
                ->whereNull('verified_at')
                ->update(['verified_at' => now()]);
        }

        return $this->generateAndSend($user);
    }
}