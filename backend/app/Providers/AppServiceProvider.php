<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('face', fn (Request $request) => Limit::perMinute(5)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return rtrim((string) env('FRONTEND_URL', 'http://127.0.0.1:8001'), '/')
                .'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]);
        });
    }
}
