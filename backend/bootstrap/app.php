<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
        $middleware->redirectGuestsTo(
            fn (Request $request): ?string => $request->is('api', 'api/*') ? null : '/',
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $exception): bool => $request->is('api', 'api/*')
                || $request->expectsJson(),
        );
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api', 'api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Data yang dikirim belum valid.',
                'errors' => $exception->errors(),
            ], 422);
        });
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api', 'api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Sesi tidak valid. Silakan masuk kembali.',
                'errors' => [],
            ], 401);
        });
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api', 'api/*')) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $message = match ($status) {
                404 => 'Endpoint tidak ditemukan.',
                405 => 'Metode permintaan tidak didukung.',
                429 => 'Terlalu banyak percobaan. Coba kembali beberapa saat lagi.',
                default => $status < 500 ? 'Permintaan tidak dapat diproses.' : 'Terjadi kesalahan pada server.',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => [],
            ], $status);
        });
    })->create();
