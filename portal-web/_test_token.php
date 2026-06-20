<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Login by creating token
$email = 'admin@ocer-dns.test';
$user = User::where('email', $email)->first();
if (!$user) {
    // Try any user
    $user = User::first();
}
if (!$user) { echo "No user".PHP_EOL; exit; }
echo "User: {$user->uid} email={$user->email}".PHP_EOL;

// Create a personal access token
$token = $user->createToken('test-cli');
echo "Token: ".$token->plainTextToken.PHP_EOL;
