<?php

/**
 * End-to-end demo: User Creation → Approval → Activation → Login
 * Run: php scripts/demo-activation.php
 */

$baseUrl = 'http://127.0.0.1:8000/api/v1';
$tenantSlug = 'pt-demo';
$adminEmail = 'admin@demo.id';
$adminPassword = 'Password123';
$newPassword = 'Creative@2026';
$timestamp = time();
$demoEmail = "demo.aktivasi.{$timestamp}@demo.id";
$demoName = "Demo Aktivasi {$timestamp}";

function api(string $method, string $url, ?array $body = null, ?string $token = null): array
{
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'body' => json_decode($raw, true) ?? ['raw' => $raw],
    ];
}

function step(string $label, array $result): void
{
    $ok = $result['status'] >= 200 && $result['status'] < 300;
    $icon = $ok ? '✓' : '✗';
    echo "\n{$icon} {$label}\n";
    echo "   HTTP {$result['status']}\n";
    $msg = $result['body']['message'] ?? ($result['body']['data']['message'] ?? null);
    if ($msg) {
        echo "   → {$msg}\n";
    }
    if (! $ok) {
        echo '   '.json_encode($result['body'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
        exit(1);
    }
}

echo "=== DEMO AKTIVASI USER — CreativeSuite ERP ===\n";
echo "Email demo: {$demoEmail}\n";

// 1. Login admin
$login = api('POST', "{$baseUrl}/auth/login", [
    'tenant_slug' => $tenantSlug,
    'email' => $adminEmail,
    'password' => $adminPassword,
]);
step('1. Login admin', $login);
$token = $login['body']['data']['access_token'] ?? $login['body']['data']['token'] ?? null;
if (! $token) {
    echo "   Token tidak ditemukan dalam response.\n";
    exit(1);
}

// 2. Form options
$formOpts = api('GET', "{$baseUrl}/iam/form-options", null, $token);
step('2. Ambil form-options', $formOpts);
$data = $formOpts['body']['data'] ?? [];

$financeRole = null;
foreach ($data['allowed_roles'] ?? [] as $role) {
    if (stripos($role['name'] ?? '', 'Finance') !== false && stripos($role['name'] ?? '', 'Staff') !== false) {
        $financeRole = $role;
        break;
    }
}
if (! $financeRole) {
    foreach ($data['allowed_roles'] ?? [] as $role) {
        $financeRole = $role;
        break;
    }
}
if (! $financeRole) {
    echo "   Tidak ada role tersedia.\n";
    exit(1);
}
echo "   Role: {$financeRole['name']} (id={$financeRole['id']})\n";

$branchId = $data['branches'][0]['id'] ?? null;
$deptPublicId = $data['departments'][0]['public_id'] ?? null;

// 3. Create + submit request
$create = api('POST', "{$baseUrl}/user-creation-requests", [
    'full_name' => $demoName,
    'email' => $demoEmail,
    'phone' => '081234567890',
    'branch_id' => $branchId,
    'department_public_id' => $deptPublicId,
    'requested_role_id' => $financeRole['id'],
    'position' => 'Staff Finance',
    'notes' => 'Demo aktivasi otomatis',
    'submit' => true,
], $token);
step('3. Buat & ajukan user request', $create);
$requestPublicId = $create['body']['data']['public_id']
    ?? $create['body']['data']['id']
    ?? $create['body']['data']['request']['public_id']
    ?? null;
if (! $requestPublicId) {
    echo '   public_id tidak ditemukan: '.json_encode($create['body'])."\n";
    exit(1);
}
echo "   Request ID: {$requestPublicId}\n";

// 4. Override approve (Owner)
$approve = api('POST', "{$baseUrl}/user-creation-requests/{$requestPublicId}/override-approve", [], $token);
step('4. Override approve (Owner)', $approve);

// 5. Fetch activation token from DB
function dbLookup(string $email): array
{
    require dirname(__DIR__).'/vendor/autoload.php';
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $user = App\Modules\Core\Models\User::where('email', $email)->first();
    if (! $user) {
        return ['error' => 'user not found'];
    }
    $actToken = App\Modules\Auth\Models\UserActivationToken::where('user_id', $user->id)
        ->whereNull('used_at')->latest('id')->first();
    $otp = App\Modules\Auth\Models\UserVerificationOtp::where('user_id', $user->id)
        ->whereNull('verified_at')->latest('id')->first();

    return [
        'user_id' => $user->id,
        'account_status' => $user->account_status,
        'activation_token' => $actToken?->token,
        'token_expires' => $actToken?->expired_at?->toIso8601String(),
        'otp' => $otp?->otp_code,
        'otp_expires' => $otp?->expired_at?->toIso8601String(),
    ];
}

$dbInfo = dbLookup($demoEmail);
if (! empty($dbInfo['error']) || empty($dbInfo['activation_token'])) {
    echo "\n✗ 5. Token aktivasi tidak ditemukan di DB\n";
    echo '   '.json_encode($dbInfo)."\n";
    exit(1);
}
$activationToken = $dbInfo['activation_token'];
echo "\n✓ 5. Token aktivasi dari DB\n";
echo "   Status akun: {$dbInfo['account_status']}\n";
echo "   Token: ".substr($activationToken, 0, 16)."...\n";
echo "   Link UI: http://localhost:3000/activate?token={$activationToken}\n";

// 6. Validate token
$validate = api('POST', "{$baseUrl}/auth/activation/validate", ['token' => $activationToken]);
step('6. Validasi token aktivasi', $validate);

// 7. Set password
$setPw = api('POST', "{$baseUrl}/auth/activation/set-password", [
    'token' => $activationToken,
    'password' => $newPassword,
    'password_confirmation' => $newPassword,
]);
step('7. Set password', $setPw);
$otpSessionToken = $setPw['body']['data']['otp_session_token'] ?? null;
if (! $otpSessionToken) {
    echo "   otp_session_token tidak ditemukan.\n";
    exit(1);
}

// 8. Get OTP from DB
$dbInfo2 = dbLookup($demoEmail);
$otpCode = $dbInfo2['otp'] ?? null;
if (! $otpCode) {
    echo "\n✗ 8. OTP tidak ditemukan di DB\n";
    echo '   '.json_encode($dbInfo2)."\n";
    exit(1);
}
echo "\n✓ 8. OTP dari DB: {$otpCode}\n";

// 9. Verify OTP
$verifyOtp = api('POST', "{$baseUrl}/auth/activation/verify-otp", [
    'token' => $activationToken,
    'otp_session_token' => $otpSessionToken,
    'otp' => $otpCode,
]);
step('9. Verifikasi OTP & aktivasi akun', $verifyOtp);

// 10. Login user baru
$newLogin = api('POST', "{$baseUrl}/auth/login", [
    'tenant_slug' => $tenantSlug,
    'email' => $demoEmail,
    'password' => $newPassword,
]);
step('10. Login user baru', $newLogin);

echo "\n=== DEMO SELESAI ===\n";
echo "Email   : {$demoEmail}\n";
echo "Password: {$newPassword}\n";
echo "Link aktivasi (sudah dipakai): http://localhost:3000/activate?token={$activationToken}\n";
echo "\nUntuk demo UI manual, buat user baru lalu buka link aktivasi dari email/log.\n";