<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Enums\UserAccountStatus;
use App\Modules\Auth\Models\UserActivationToken;
use App\Modules\Auth\Notifications\AccountActivationNotification;
use App\Modules\Business\Services\EmployeeLinkService;
use App\Modules\Core\Models\User;
use App\Modules\Iam\Services\AuditLogService;
use App\Modules\Iam\Services\NotificationDispatcher;
use App\Modules\Iam\Services\WhatsAppNotifier;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserActivationService
{
    public function __construct(
        protected UserVerificationOtpService $otpService,
        protected AuditLogService $auditLog,
        protected NotificationDispatcher $notifications,
        protected WhatsAppNotifier $whatsApp,
        protected EmployeeLinkService $employeeLink,
    ) {}

    public function createActivationToken(User $user): UserActivationToken
    {
        UserActivationToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        return UserActivationToken::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'token' => hash('sha256', Str::random(64).$user->id.now()->timestamp),
            'expired_at' => now()->addHours(config('auth_activation.activation_token_hours', 24)),
        ]);
    }

    public function sendActivationNotifications(User $user, UserActivationToken $token, ?User $actor = null): void
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $activationUrl = "{$frontendUrl}/activate?token={$token->token}";

        $user->notify(new AccountActivationNotification($activationUrl, $user->full_name));

        if (config('auth_activation.whatsapp.enabled', false)) {
            $this->whatsApp->send(
                $user,
                'Aktivasi Akun CreativeSuite ERP',
                "Halo {$user->full_name}\n\nAkun CreativeSuite ERP Anda telah disetujui.\n\nSilakan cek email untuk melakukan aktivasi akun.\n\nTerima kasih.",
            );
        }

        $this->auditLog->record(
            $actor ?? $user,
            'ACTIVATION_LINK_SENT',
            'User',
            $user->id,
            $user->public_id,
            null,
            ['email' => $user->email, 'expires_at' => $token->expired_at->toIso8601String()],
            $user->default_company_id,
        );
    }

    public function validateToken(string $token): array
    {
        $record = $this->findValidToken($token);

        if (! $record->opened_at) {
            $record->update(['opened_at' => now()]);
            $this->auditLog->record(
                $record->user,
                'ACTIVATION_OPENED',
                'User',
                $record->user_id,
                $record->user->public_id,
                null,
                ['token_id' => $record->id],
                $record->user->default_company_id,
            );
        }

        return [
            'valid' => true,
            'full_name' => $record->user->full_name,
            'email' => $this->maskEmail($record->user->email),
            'expired_at' => $record->expired_at->toIso8601String(),
        ];
    }

    public function setPassword(string $token, string $password): array
    {
        $record = $this->findValidToken($token);
        $user = $record->user;

        if ($user->account_status !== UserAccountStatus::PendingActivation->value) {
            throw new ApiException('Akun tidak dalam status pending activation.', 422, 'INVALID_STATUS');
        }

        DB::transaction(function () use ($user, $password) {
            $user->update([
                'password' => $password,
                'must_change_password' => false,
            ]);

            $this->auditLog->record(
                $user,
                'PASSWORD_CREATED',
                'User',
                $user->id,
                $user->public_id,
                null,
                ['via' => 'activation'],
                $user->default_company_id,
            );
        });

        $otpSession = $this->otpService->generateAndSend($user);

        return [
            'otp_session_token' => $otpSession['session_token'],
            'otp_expires_in' => config('auth_activation.otp_expire_minutes', 5) * 60,
            'email' => $this->maskEmail($user->email),
        ];
    }

    public function verifyOtpAndActivate(string $activationToken, string $sessionToken, string $otp): array
    {
        $record = $this->findValidToken($activationToken);
        $user = $record->user;

        $this->otpService->verify($user, $sessionToken, $otp);

        return DB::transaction(function () use ($record, $user) {
            $record->update(['used_at' => now()]);

            $user->update([
                'account_status' => UserAccountStatus::Active->value,
                'is_active' => true,
                'activated_at' => now(),
                'email_verified_at' => now(),
            ]);

            $this->auditLog->record(
                $user,
                'OTP_VERIFIED',
                'User',
                $user->id,
                $user->public_id,
                ['account_status' => UserAccountStatus::PendingActivation->value],
                ['account_status' => UserAccountStatus::Active->value],
                $user->default_company_id,
            );

            $this->auditLog->record(
                $user,
                'ACCOUNT_ACTIVATED',
                'User',
                $user->id,
                $user->public_id,
                null,
                ['activated_at' => $user->activated_at?->toIso8601String()],
                $user->default_company_id,
            );

            $this->employeeLink->ensureForUser($user->fresh());

            $this->notifications->notifyUsers(
                collect([$user]),
                'ACCOUNT_ACTIVATED',
                'Akun berhasil diaktifkan',
                'Akun CreativeSuite ERP Anda telah aktif. Silakan login.',
            );

            return [
                'activated' => true,
                'message' => 'Akun berhasil diaktifkan. Silakan login.',
            ];
        });
    }

    public function resendOtp(string $activationToken, string $sessionToken): array
    {
        $record = $this->findValidToken($activationToken);
        $otpSession = $this->otpService->resend($record->user, $sessionToken);

        return [
            'otp_session_token' => $otpSession['session_token'],
            'otp_expires_in' => config('auth_activation.otp_expire_minutes', 5) * 60,
        ];
    }

    protected function findValidToken(string $token): UserActivationToken
    {
        $record = UserActivationToken::query()
            ->where('token', $token)
            ->with('user')
            ->first();

        if (! $record || ! $record->isValid()) {
            throw new ApiException('Token aktivasi tidak valid atau sudah kadaluarsa.', 400, 'INVALID_ACTIVATION_TOKEN');
        }

        return $record;
    }

    protected function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $masked = substr($local, 0, 2).str_repeat('*', max(strlen($local) - 2, 1));

        return "{$masked}@{$domain}";
    }
}