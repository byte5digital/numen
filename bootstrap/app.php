<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Global API rate limiting: 60 requests per minute
        $middleware->api(prepend: [
            'throttle:60,1',
        ]);

        // Register named middleware aliases
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'can' => \App\Http\Middleware\CheckPermission::class,
        ]);

        // Resolve active space on all web + api requests
        $middleware->web(append: [
            \App\Http\Middleware\ResolveActiveSpace::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\ResolveActiveSpace::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\App\Services\AI\Exceptions\CostLimitExceededException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'ai_cost_limit_exceeded',
                    'message' => $e->getMessage(),
                    'period' => $e->period,
                ], 402);
            }

            return response($e->getMessage(), 402);
        });
    })->create();
