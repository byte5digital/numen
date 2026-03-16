<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\ResolveCurrentSpace::class,
        ]);

        // Global API rate limiting: 60 requests per minute
        $middleware->api(prepend: [
            'throttle:60,1',
        ]);

        // Register named middleware aliases
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'permission' => \App\Http\Middleware\RequirePermission::class,
            'resolve-space' => \App\Http\Middleware\ResolveCurrentSpace::class,
            'set-locale' => \App\Http\Middleware\SetLocaleFromRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return consistent JSON 403 for permission denials (API requests)
        $exceptions->render(function (\App\Exceptions\PermissionDeniedException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Forbidden',
                    'required' => $e->permission,
                ], 403);
            }

            return response($e->getMessage(), 403);
        });

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
