<?php

namespace App\Modules\Auth\Services\Contracts;

use App\Modules\Core\Models\User;

interface EmailVerificationServiceInterface
{
    public function sendVerificationNotification(User $user): void;

    public function verify(User $user, string $hash): string;
}