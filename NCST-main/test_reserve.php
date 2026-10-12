<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    app(App\Services\TransactionService::class)->requestEquipment(1, 1);
    echo "Success\n";
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}
