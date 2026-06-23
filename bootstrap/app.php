<?php

use App\Http\Middleware\ActivityLoggerMiddleware;
use App\Http\Middleware\CacheResponseMiddleware;
use App\Http\Middleware\ChurchAdminMiddleware;
use App\Http\Middleware\MinistryAdminMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\ZoneAdminMiddleware;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\ApiVersionMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
      ->withRouting(
        web:      __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health:   '/up',
        // api routes split into two files: main API + admin-only API
        then: function () {
            Route::middleware('api')->group(base_path('routes/auth.php'));
             Route::middleware('api')->group(base_path('routes/analytics.php'));
              Route::middleware('api')->group(base_path('routes/system.php'));
            Route::middleware('api')->group(base_path('routes/api.php'));
        },
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // ---------------------------------------------------------------------------
        // Named middleware aliases
        // ---------------------------------------------------------------------------
        $middleware->alias([
            'ministry.admin'    => MinistryAdminMiddleware::class,
            'zone.admin'        => ZoneAdminMiddleware::class,
            'church.admin'      => ChurchAdminMiddleware::class,
            'activity.logger'   => ActivityLoggerMiddleware::class,
            'rate.limit'        => RateLimitMiddleware::class,
            'cache.response'    => CacheResponseMiddleware::class,
            'idempotency'    => IdempotencyMiddleware::class,
            'api.version'    => ApiVersionMiddleware::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {

        // ---------------------------------------------------------------------------
        // Unified JSON error responses for API
        // ---------------------------------------------------------------------------

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The requested resource was not found.',
                ], 404);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please provide a valid token.',
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->expectsJson() && app()->environment('production')) {
                return response()->json([
                    'success' => false,
                    'message' => 'An internal server error occurred.',
                ], 500);
            }
        });

    })->create();
