<?php

namespace App\Modules\Auth\Services\Contracts;

interface RegistrationServiceInterface
{
    public function registerCompany(array $data): array;

    public function registerUser(array $data): array;
}