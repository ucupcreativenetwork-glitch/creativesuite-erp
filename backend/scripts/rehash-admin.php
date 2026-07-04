<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Modules\Core\Models\User::where('email', 'admin@demo.id')->first();
if (! $user) {
    echo "User not found\n";
    exit(1);
}
$user->password = Illuminate\Support\Facades\Hash::make('Password123');
$user->save();
echo "Updated user {$user->id} ({$user->email})\n";