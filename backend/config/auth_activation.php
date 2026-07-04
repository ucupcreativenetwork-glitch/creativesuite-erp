<?php

return [
    'activation_token_hours' => (int) env('ACTIVATION_TOKEN_HOURS', 24),
    'otp_expire_minutes' => (int) env('OTP_EXPIRE_MINUTES', 5),
    'otp_max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'otp_length' => 6,

    'lockout' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
    ],

    'password_reset_expire_minutes' => (int) env('PASSWORD_RESET_EXPIRE_MINUTES', 30),

    'whatsapp' => [
        'enabled' => env('IAM_WHATSAPP_ENABLED', false),
        'webhook_url' => env('IAM_WHATSAPP_WEBHOOK_URL'),
    ],
];