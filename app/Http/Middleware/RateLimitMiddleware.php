<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    use ApiResponse;

    public function __construct(protected RateLimiter $limiter) {}

    /**
     * Usage: ->middleware('rate.limit:api')
     *        ->middleware('rate.limit:auth')
     *        ->middleware('rate.limit:analytics')
     *        ->middleware('rate.limit:export')
     */
    public function handle(Request $request, Closure $next, string $type = 'api'): Response
    {
        $config   = config("church.rate_limits.{$type}", ['attempts' => 60, 'per_minutes' => 1]);
        $key      = $this->resolveKey($request, $type);
        $maxAttempts = $config['attempts'];
        $decaySeconds = $config['per_minutes'] * 60;

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return $this->errorResponse(
                "Too many requests. Please retry after {$retryAfter} seconds.",
                429,
                ['retry_after' => $retryAfter, 'limit' => $maxAttempts]
            );
        }

        $this->limiter->hit($key, $decaySeconds);

        $response = $next($request);

        $remaining = max(0, $maxAttempts - $this->limiter->attempts($key));

        return $response
            ->header('X-RateLimit-Limit',     $maxAttempts)
            ->header('X-RateLimit-Remaining', $remaining);
    }

    private function resolveKey(Request $request, string $type): string
    {
        // Auth endpoints keyed by IP; API endpoints keyed by user ID if authenticated
        $identifier = $type === 'auth'
            ? $request->ip()
            : ($request->user()?->id ?? $request->ip());

        return "rate_limit:{$type}:{$identifier}";
    }
}
