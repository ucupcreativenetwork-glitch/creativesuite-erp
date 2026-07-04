<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Notifications\VerifyEmailNotification;
use App\Modules\Auth\Services\Contracts\EmailVerificationServiceInterface;
use App\Modules\Core\Models\User;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\URL;

class EmailVerificationService implements EmailVerificationServiceInterface
{
    public function sendVerificationNotification(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw new ApiException('Email already verified.', 409, 'EMAIL_ALREADY_VERIFIED');
        }

        $user->notify(new VerifyEmailNotification);
    }

    public function verify(User $user, string $hash): string
    {
        if ($user->hasVerifiedEmail()) {
            throw new ApiException('Email already verified.', 409, 'EMAIL_ALREADY_VERIFIED');
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new ApiException('Invalid verification link.', 400, 'INVALID_VERIFICATION');
        }

        $user->markEmailAsVerified();

        return 'Email verified successfully.';
    }

    public static function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'api.v1.auth.verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->public_id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );
    }
}