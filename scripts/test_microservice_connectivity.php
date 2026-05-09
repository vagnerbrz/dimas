<?php

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sendTest = in_array('--send-test', $argv, true);

$exitCode = Artisan::call('print:diagnose', [
    '--send-test' => $sendTest,
]);

echo Artisan::output();

exit($exitCode);
