<?php

use App\Jobs\DetectOfflineDevicesJob;
use App\Jobs\ExpireStaleCommandsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pembersihan otomatis command kedaluwarsa & deteksi perangkat offline setiap menit
Schedule::job(new ExpireStaleCommandsJob)->everyMinute();
Schedule::job(new DetectOfflineDevicesJob)->everyMinute();
