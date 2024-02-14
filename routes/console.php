<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('logs:clear', function () {
    $logs = [
        'laravel',
        'translation'
    ];

    // Clean every log
    foreach ($logs as $log) {
        $handle = fopen(storage_path("logs/$log.log"), 'w+');
        fclose($handle);
    }
})->describe('Clear log files');
