<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Auth\Notifications\AccountActivationNotification;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Mail;

$to = $argv[1] ?? 'admin@creativenetwork.my.id';

echo "=== Test SMTP CreativeSuite ERP ===\n";
echo "Mailer : ".config('mail.default')."\n";
echo "Host   : ".config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port')."\n";
echo "From   : ".config('mail.from.address')."\n";
echo "To     : {$to}\n\n";

try {
    $user = User::where('email', 'admin@demo.id')->first() ?? User::first();
    $user->email = $to;
    $user->notifyNow(new AccountActivationNotification(
        config('app.frontend_url').'/activate?token=TEST_TOKEN_DESIGN_PREVIEW',
        $user->full_name ?? 'Demo User',
    ));
    echo "OK: Email aktivasi (template baru + link) terkirim.\n";
} catch (Throwable $e) {
    echo "FAIL: {$e->getMessage()}\n";
    exit(1);
}

echo "\nSelesai. Cek inbox (dan folder Spam) {$to}\n";