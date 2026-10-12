<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap/app.php';
$app = app();
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $s = app(App\Services\TransactionService::class);
    $res = $s->requestEquipment(1, 1, 1, 'Class Project / Laboratory', '3 Hours');
    echo 'Success: ' . $res->id;
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
