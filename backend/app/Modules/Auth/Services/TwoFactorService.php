<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Services\Contracts\TwoFactorServiceInterface;
use App\Modules\Core\Models\User;
use App\Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Exceptions\ApiException;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService implements TwoFactorServiceInterface
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected Google2FA $google2fa,
    ) {}

    public function setup(User $user): array
    {
        if ($user->mfa_enabled) {
            throw new ApiException('Two-factor authentication is already enabled.', 409, 'MFA_ALREADY_ENABLED');
        }

        $secret = $this->google2fa->generateSecretKey();

        $user->update(['mfa_secret' => $secret]);

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'qr_code_svg' => $this->generateQrSvg($qrCodeUrl),
        ];
    }

    public function confirm(User $user, string $code): array
    {
        if ($user->mfa_enabled) {
            throw new ApiException('Two-factor authentication is already enabled.', 409, 'MFA_ALREADY_ENABLED');
        }

        if (! $user->mfa_secret) {
            throw new ApiException('Two-factor setup not initiated.', 400, 'MFA_NOT_INITIATED');
        }

        if (! $this->google2fa->verifyKey($user->mfa_secret, $code)) {
            throw new ApiException('Invalid authentication code.', 400, 'INVALID_MFA_CODE');
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'mfa_enabled' => true,
            'mfa_recovery_codes' => $recoveryCodes,
        ]);

        return [
            'mfa_enabled' => true,
            'recovery_codes' => $recoveryCodes,
        ];
    }

    public function verifyChallenge(string $mfaToken, string $code, ?string $ip = null): array
    {
        $challenge = Cache::get('mfa_challenge:'.$mfaToken);

        if (! $challenge) {
            throw new ApiException('Invalid or expired MFA token.', 401, 'INVALID_MFA_TOKEN');
        }

        /** @var User|null $user */
        $user = User::query()->find($challenge['user_id']);

        if (! $user || ! $user->mfa_enabled) {
            throw new ApiException('Invalid MFA challenge.', 401, 'INVALID_MFA_TOKEN');
        }

        $valid = $this->google2fa->verifyKey($user->mfa_secret, $code);

        if (! $valid) {
            $valid = $this->consumeRecoveryCode($user, $code);
        }

        if (! $valid) {
            throw new ApiException('Invalid authentication code.', 401, 'INVALID_MFA_CODE');
        }

        Cache::forget('mfa_challenge:'.$mfaToken);

        $this->userRepository->updateLastLogin($user, $ip);

        $token = JWTAuth::fromUser($user);

        return [
            'mfa_required' => false,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => $user->load(['roles', 'defaultCompany', 'defaultBranch']),
        ];
    }

    public function disable(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw new ApiException('Invalid password.', 401, 'INVALID_PASSWORD');
        }

        $user->update([
            'mfa_enabled' => false,
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
        ]);
    }

    public function regenerateRecoveryCodes(User $user, string $password): array
    {
        if (! $user->mfa_enabled) {
            throw new ApiException('Two-factor authentication is not enabled.', 400, 'MFA_NOT_ENABLED');
        }

        if (! Hash::check($password, $user->password)) {
            throw new ApiException('Invalid password.', 401, 'INVALID_PASSWORD');
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->update(['mfa_recovery_codes' => $recoveryCodes]);

        return ['recovery_codes' => $recoveryCodes];
    }

    protected function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(4).'-'.Str::random(4)))
            ->all();
    }

    protected function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->mfa_recovery_codes ?? [];
        $normalized = Str::upper(str_replace(' ', '', $code));

        if (! in_array($normalized, $codes, true)) {
            return false;
        }

        $user->update([
            'mfa_recovery_codes' => array_values(array_diff($codes, [$normalized])),
        ]);

        return true;
    }

    protected function generateQrSvg(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd,
        );

        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }
}