<?php

return [
    'whatsapp' => [
        'enabled' => env('IAM_WHATSAPP_ENABLED', false),
        'webhook_url' => env('IAM_WHATSAPP_WEBHOOK_URL'),
    ],

    'expo_push' => [
        'enabled' => env('IAM_EXPO_PUSH_ENABLED', true),
        'api_url' => env('IAM_EXPO_PUSH_API_URL', 'https://exp.host/--/api/v2/push/send'),
        'access_token' => env('IAM_EXPO_PUSH_ACCESS_TOKEN'),
        'android_channel_id' => env('IAM_EXPO_PUSH_ANDROID_CHANNEL', 'hr-alerts'),
    ],
];