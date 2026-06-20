<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Domain\Profile\UserWorkspaceService;
use App\Models\User;
try {
    $user = User::first();
    if (!$user) { echo 'No user'.PHP_EOL; exit; }
    echo 'User uid: '.$user->uid.PHP_EOL;
    $svc = new UserWorkspaceService();
    $r = $svc->dnsEndpoints($user->uid, '27b438');
    var_dump($r);
} catch (Throwable $e) {
    echo 'ERR: '.$e->getMessage().PHP_EOL;
    echo $e->getTraceAsString().PHP_EOL;
}
