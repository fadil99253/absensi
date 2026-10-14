<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\FaceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/dashboard', DashboardController::class);
    Route::post('/face/challenge', [FaceController::class, 'challenge']);
    Route::post('/attendance', [AttendanceController::class, 'store'])->middleware('throttle:face');
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/reports/attendance', [ReportController::class, 'index'])->middleware('role:admin');
    Route::get('/reports/attendance/export/{format}', [ReportController::class, 'export'])
        ->whereIn('format', ['pdf', 'xlsx'])
        ->middleware('role:admin');

    Route::middleware('role:admin')->group(function (): void {
        Route::apiResource('employees', EmployeeController::class);
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);
    });

    Route::post('/employees/{employee}/face', [FaceController::class, 'register'])
        ->middleware('throttle:face');
});
