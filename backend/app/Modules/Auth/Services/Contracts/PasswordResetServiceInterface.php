<?php

namespace App\Modules\Auth\Services\Contracts;

interface PasswordResetServiceInterface
{
    public function sendResetLink(string $companyIdentifier, string $email): string;

    public function resetPassword(string $companyIdentifier, string $email, string $token, string $password): string;
}