<?php

return [
    /** Slug tenant sistem untuk akun platform admin. */
    'tenant_slug' => env('PLATFORM_TENANT_SLUG', 'platform'),

    /** Slug tenant demo yang dihapus oleh erp:purge-demo. */
    'demo_tenant_slug' => env('DEMO_TENANT_SLUG', 'pt-demo'),

    'system_tenant_name' => env('PLATFORM_TENANT_NAME', 'CreativeSuite Platform'),

    /** Nama/alias yang diterima di form login untuk akun admin SaaS. */
    'login_aliases' => [
        'CreativeSuite Platform',
        'Admin SaaS',
        'Platform Admin',
    ],

    /** Nama/alias yang diterima di form login untuk tenant demo. */
    'demo_login_aliases' => [
        'Demo Agency',
        'PT Demo Agency',
        'Demo',
    ],

    'demo_admin_email' => env('DEMO_ADMIN_EMAIL', 'admin@demo.id'),
    'demo_admin_password' => env('DEMO_ADMIN_PASSWORD', 'Password123'),
];