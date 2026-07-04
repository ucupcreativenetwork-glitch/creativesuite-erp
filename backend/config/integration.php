<?php

return [
    'api_key_prefix' => env('INTEGRATION_API_KEY_PREFIX', 'cs_live_'),

    'available_scopes' => [
        'attendance.read',
        'attendance.write',
        'purchasing.read',
        'purchasing.write',
        'inventory.read',
        'connector.push',
    ],

    'webhook_events' => [
        'attendance.recorded',
        'attendance.imported',
        'purchasing.order.created',
        'purchasing.order.received',
        'inventory.low_stock',
        'connector.received',
    ],

    'connector_types' => [
        'zkteco' => 'ZKTeco / eSSL Fingerprint',
        'hikvision' => 'Hikvision Access Control',
        'custom' => 'Custom / Generic JSON',
    ],

    'connector_match_fields' => [
        'device_pin' => 'PIN Mesin Absensi',
        'employee_number' => 'Nomor Karyawan ERP',
        'pin' => 'PIN (legacy → device_pin / nomor karyawan)',
    ],

    'webhook' => [
        'max_attempts' => (int) env('INTEGRATION_WEBHOOK_MAX_ATTEMPTS', 5),
        'retry_delay_minutes' => (int) env('INTEGRATION_WEBHOOK_RETRY_MINUTES', 5),
    ],
];