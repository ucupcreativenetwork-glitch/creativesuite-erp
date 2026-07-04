<?php

namespace App\Modules\Auth\Services\Contracts;

interface AuthServiceInterface
{
    public function login(string $companyIdentifier, string $email, string $password, ?string $ip = null): array;

    public function logout(): void;

    public function refresh(): array;

    public function me(): array;
}