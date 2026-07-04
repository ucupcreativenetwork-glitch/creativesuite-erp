<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Notifications\ResetPasswordNotification;
use App\Modules\Auth\Services\Contracts\PasswordResetServiceInterface;
use App\Modules\Iam\Services\AuditLogService;
use App\Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Exceptions\ApiException;
use App\Support\Tenant\CompanyIdentifierResolver;
use App\Support\Tenant\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetService implements PasswordResetServiceInterface
{
    public function __construct(
        protected CompanyIdentifierResolver $companyResolver,
        protected UserRepositoryInterface $userRepository,
        protected TenantManager $tenantManager,
        protected AuditLogService $auditLog,
    ) {}

    public function sendResetLink(string $companyIdentifier, string $email): string
    {
        $resolution = $this->companyResolver->resolve($companyIdentifier);

        if ($resolution['ambiguous']) {
            throw new ApiException(
                'Nama perusahaan tidak spesifik. Gunakan nama lengkap perusahaan Anda.',
                422,
                'AMBIGUOUS_COMPANY',
            );
        }

        $tenant = $resolution['tenant'];

        if (! $tenant) {
            return 'If the email exists, a password reset link has been sent.';
        }

        $this->tenantManager->set($tenant);

        $user = $this->userRepository->findByEmail($tenant->id, $email);

        if (! $user) {
            return 'If the email exists, a password reset link has been sent.';
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()],
        );

        $user->notify(new ResetPasswordNotification($token, $tenant->name));

        return 'If the email exists, a password reset link has been sent.';
    }

    public function resetPassword(string $companyIdentifier, string $email, string $token, string $password): string
    {
        $resolution = $this->companyResolver->resolve($companyIdentifier);

        if ($resolution['ambiguous']) {
            throw new ApiException(
                'Nama perusahaan tidak spesifik. Gunakan nama lengkap perusahaan Anda.',
                422,
                'AMBIGUOUS_COMPANY',
            );
        }

        $tenant = $resolution['tenant'];

        if (! $tenant) {
            throw new ApiException('Invalid reset token.', 400, 'INVALID_TOKEN');
        }

        $this->tenantManager->set($tenant);

        $user = $this->userRepository->findByEmail($tenant->id, $email);

        if (! $user) {
            throw new ApiException('Invalid reset token.', 400, 'INVALID_TOKEN');
        }

        $record = DB::table('password_reset_tokens')
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if (! $record || ! Hash::check($token, $record->token)) {
            throw new ApiException('Invalid reset token.', 400, 'INVALID_TOKEN');
        }

        $expireMinutes = config('auth_activation.password_reset_expire_minutes', 30);
        if (now()->diffInMinutes($record->created_at) > $expireMinutes) {
            throw new ApiException('Reset token has expired.', 400, 'TOKEN_EXPIRED');
        }

        $user->update([
            'password' => $password,
            'must_change_password' => false,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        DB::table('password_reset_tokens')
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->delete();

        $this->auditLog->record(
            $user,
            'PASSWORD_CHANGED',
            'User',
            $user->id,
            $user->public_id,
            null,
            ['via' => 'reset_password'],
            $user->default_company_id,
        );

        return 'Password has been reset successfully.';
    }
}