<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$reservations = \App\Models\Reservation::whereHas('resource', function($q) { 
    $q->where('name', 'circle/architectural templates')
      ->orWhere('name', 'Missing Parts'); 
})->get(); 
foreach($reservations as $res) { 
    $res->resource->increment('stock', $res->quantity); 
    $res->delete(); 
}
echo 'Done';
