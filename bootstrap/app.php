<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pelanggaran whitelist data_sources adalah penolakan akses, bukan
        // kesalahan server — 500 menyembunyikan sebabnya dari pengguna.
        $exceptions->render(function (\App\Exceptions\DataSourceException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['error' => $e->getMessage()], 403)
                : response()->view('errors.403', ['exception' => $e], 403);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
