<?php

namespace App\Modules\Auth\Services\Contracts;

use App\Modules\Core\Models\User;

interface TwoFactorServiceInterface
{
    public function setup(User $user): array;

    public function confirm(User $user, string $code): array;

    public function verifyChallenge(string $mfaToken, string $code, ?string $ip = null): array;

    public function disable(User $user, string $password): void;

    public function regenerateRecoveryCodes(User $user, string $password): array;
}