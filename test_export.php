<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Exports\SalesExport;
use Carbon\Carbon;

try {
    $data = (new SalesExport(
        Carbon::now()->startOfMonth(),
        Carbon::now()
    ))->collection();
    echo 'BERHASIL: ' . $data->count() . ' baris data';
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . ' di baris ' . $e->getLine();
}
