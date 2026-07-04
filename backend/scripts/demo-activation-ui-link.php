<?php

/** Buat user + approve, cetak link aktivasi untuk demo UI (token belum dipakai) */

$baseUrl = 'http://127.0.0.1:8000/api/v1';
$timestamp = time();
$demoEmail = "ui.demo.{$timestamp}@demo.id";

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
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => json_decode($raw, true)];
}

$login = api('POST', "{$baseUrl}/auth/login", [
    'tenant_slug' => 'pt-demo',
    'email' => 'admin@demo.id',
    'password' => 'Password123',
]);
$token = $login['body']['data']['access_token'] ?? null;
if (! $token) {
    echo "Login gagal\n";
    exit(1);
}

$form = api('GET', "{$baseUrl}/iam/form-options", null, $token);
$roleId = $form['body']['data']['allowed_roles'][0]['id'] ?? 10;

$create = api('POST', "{$baseUrl}/user-creation-requests", [
    'full_name' => "UI Demo {$timestamp}",
    'email' => $demoEmail,
    'requested_role_id' => $roleId,
    'position' => 'Staff',
    'submit' => true,
], $token);

$reqId = $create['body']['data']['id'] ?? null;
api('POST', "{$baseUrl}/user-creation-requests/{$reqId}/override-approve", [], $token);

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Modules\Core\Models\User::where('email', $demoEmail)->first();
$act = App\Modules\Auth\Models\UserActivationToken::where('user_id', $user->id)->whereNull('used_at')->latest('id')->first();

echo "=== LINK DEMO UI ===\n";
echo "Email : {$demoEmail}\n";
echo "Status: {$user->account_status}\n";
echo "Link  : http://localhost:3000/activate?token={$act->token}\n";
echo "Password yang akan diset: Creative@2026\n";