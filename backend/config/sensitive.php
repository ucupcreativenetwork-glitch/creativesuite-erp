<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kebijakan data sensitif
    |--------------------------------------------------------------------------
    |
    | - encrypted: reversible (Laravel AES-256-CBC) — dipakai saat nilai perlu dibaca kembali
    | - hashed: one-way (bcrypt/argon atau sha256) — token/OTP/API key
    |
    */
    'encrypted_attributes' => [
        \App\Modules\Core\Models\Company::class => ['npwp', 'nitku', 'phone'],
        \App\Modules\Core\Models\User::class => ['phone', 'mfa_secret', 'mfa_recovery_codes'],
        \App\Modules\Business\Models\Employee::class => ['bpjs_number', 'phone'],
        \App\Modules\Business\Models\CrmAccount::class => ['npwp', 'phone'],
        \App\Modules\Integration\Models\WebhookEndpoint::class => ['secret'],
    ],

    'hashed_attributes' => [
        \App\Modules\Auth\Models\UserVerificationOtp::class => ['otp_code', 'session_token'],
        \App\Modules\Integration\Models\IntegrationApiKey::class => ['key_hash'],
        \App\Modules\Integration\Models\ConnectorConfig::class => ['ingest_token'],
    ],
];