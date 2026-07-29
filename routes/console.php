<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', static function (): void {
    echo Inspiring::quote().PHP_EOL;
})->purpose('Display an inspiring quote');
