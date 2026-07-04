<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$p = config('mail.mailers.smtp.password');
echo 'password_length='.strlen($p)."\n";
echo 'password_ends_with_hash='.(str_ends_with($p, '#') ? 'yes' : 'no')."\n";
echo 'scheme='.config('mail.mailers.smtp.scheme')."\n";