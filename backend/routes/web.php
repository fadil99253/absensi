<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'HadirKu API aktif.',
        'data' => ['api' => '/api'],
    ]);
});

Route::get('/up', function () {
    return response()->json([
        'success' => true,
        'message' => 'HadirKu API aktif.',
        'data' => ['status' => 'ok'],
    ]);
});
