<?php

use Illuminate\Support\Facades\Route;

Route::get('/styles.css', function () {
    return response()->file(base_path('../frontend/styles.css'), [
        'Content-Type' => 'text/css; charset=utf-8',
    ]);
});

Route::get('/app.js', function () {
    return response()->file(base_path('../frontend/app.js'), [
        'Content-Type' => 'text/javascript; charset=utf-8',
    ]);
});

Route::get('/', function () {
    return response()->file(base_path('../frontend/index.html'), [
        'Content-Type' => 'text/html; charset=utf-8',
    ]);
});
