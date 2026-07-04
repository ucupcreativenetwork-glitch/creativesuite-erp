<?php

namespace App\Support\Security;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class SensitiveData
{
    public static function hash(string $value): string
    {
        return Hash::make($value);
    }

    public static function verifyHash(string $value, string $hash): bool
    {
        return Hash::check($value, $hash);
    }

    public static function digest(string $value): string
    {
        return hash('sha256', $value);
    }

    public static function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    public static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    public static function isEncrypted(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}