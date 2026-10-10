<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('hadirku:status', function (): void {
    $this->info('Backend HadirKu siap digunakan.');
})->purpose('Tampilkan status aplikasi HadirKu');
