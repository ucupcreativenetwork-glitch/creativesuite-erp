<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Enums\UserAccountStatus;
use App\Modules\Auth\Services\Contracts\AuthServiceInterface;
use App\Modules\Business\Services\EmployeeLinkService;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\User;
use App\Modules\Core\Repositories\Contracts\TenantRepositoryInterface;
use App\Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\Iam\Services\AuditLogService;
use App\Support\Exceptions\ApiException;
use App\Support\Tenant\CompanyIdentifierResolver;
use App\Support\Tenant\TenantManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        protected TenantRepositoryInterface $tenantRepository,
        protected UserRepositoryInterface $userRepository,
        protected TenantManager $tenantManager,
        protected LoginLockoutService $lockout,
        protected AuditLogService $auditLog,
        protected EmployeeLinkService $employeeLink,
        protected CompanyIdentifierResolver $companyResolver,
    ) {}

    public function login(string $companyIdentifier, string $email, string $password, ?string $ip = null): array
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
            throw new ApiException('Invalid credentials.', 401, 'INVALID_CREDENTIALS');
        }

        if (in_array($tenant->status, [TenantStatus::Suspended, TenantStatus::Cancelled])) {
            throw new ApiException('Tenant is not active.', 403, 'TENANT_SUSPENDED');
        }

        $this->tenantManager->set($tenant);

        $user = $this->userRepository->findByEmail($tenant->id, $email);

        if (! $user) {
            throw new ApiException('Invalid credentials.', 401, 'INVALID_CREDENTIALS');
        }

        $this->lockout->assertNotLocked($user);

        if ($user->account_status === UserAccountStatus::PendingActivation->value) {
            throw new ApiException(
                'Akun belum diaktifkan. Cek email Anda untuk link aktivasi.',
                403,
                'PENDING_ACTIVATION',
            );
        }

        if (! $user->is_active || $user->account_status === UserAccountStatus::Disabled->value) {
            throw new ApiException('Invalid credentials.', 401, 'INVALID_CREDENTIALS');
        }

        if ($user->account_status === UserAccountStatus::Suspended->value) {
            throw new ApiException('Akun ditangguhkan. Hubungi administrator.', 403, 'ACCOUNT_SUSPENDED');
        }

        if (! auth('api')->attempt([
            'email' => $email,
            'password' => $password,
            'tenant_id' => $tenant->id,
        ])) {
            $this->lockout->recordFailedAttempt($user);
            throw new ApiException('Invalid credentials.', 401, 'INVALID_CREDENTIALS');
        }

        /** @var User $authenticated */
        $authenticated = auth('api')->user();

        $this->lockout->clearAttempts($authenticated);

        if ($authenticated->mfa_enabled) {
            $mfaToken = (string) Str::uuid();
            Cache::put($this->mfaCacheKey($mfaToken), [
                'user_id' => $authenticated->id,
                'tenant_id' => $tenant->id,
            ], now()->addMinutes(5));

            auth('api')->logout();

            return [
                'mfa_required' => true,
                'mfa_token' => $mfaToken,
                'message' => 'Two-factor authentication required.',
            ];
        }

        $isFirstLogin = $authenticated->last_login_at === null;
        $this->userRepository->updateLastLogin($authenticated, $ip);

        if ($isFirstLogin) {
            $this->auditLog->record(
                $authenticated,
                'FIRST_LOGIN',
                'User',
                $authenticated->id,
                $authenticated->public_id,
                null,
                ['ip' => $ip],
                $authenticated->default_company_id,
            );
        }

        if (! $authenticated->is_platform_admin) {
            $this->employeeLink->ensureForUser($authenticated);
        }

        return $this->tokenPayload($authenticated);
    }

    public function logout(): void
    {
        auth('api')->logout();
    }

    public function refresh(): array
    {
        $token = auth('api')->refresh();
        /** @var User $user */
        $user = auth('api')->user();

        return $this->tokenPayload($user, $token);
    }

    public function me(): array
    {
        /** @var User $user */
        $user = auth('api')->user();

        $user->load(['roles.permissions', 'defaultCompany', 'defaultBranch', 'companies', 'tenant']);

        return [
            'user' => $user,
            'tenant' => $user->tenant,
            'company' => $user->defaultCompany,
            'branch' => $user->defaultBranch,
        ];
    }

    protected function tokenPayload(User $user, ?string $token = null): array
    {
        $token ??= JWTAuth::fromUser($user);

        $user->load(['roles', 'defaultCompany', 'defaultBranch', 'tenant']);

        return [
            'mfa_required' => false,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => $user,
            'tenant' => $user->tenant,
            'company' => $user->defaultCompany,
            'branch' => $user->defaultBranch,
            'must_change_password' => (bool) $user->must_change_password,
            'email_verified' => $user->email_verified_at !== null,
            'account_status' => $user->account_status,
        ];
    }

    protected function mfaCacheKey(string $mfaToken): string
    {
        return 'mfa_challenge:'.$mfaToken;
    }
}