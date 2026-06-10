<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    use ApiResponse;

    /** How long a processed key is remembered (24 hours) */
    private const KEY_TTL = 86_400;

    /** How long to hold the in-flight lock to stop concurrent duplicates (30 s) */
    private const LOCK_TTL = 30;

    /** Maximum byte-length of a client-supplied key */
    private const MAX_KEY_LENGTH = 255;

    /** Request header name */
    private const HEADER = 'Idempotency-Key';

    /**
     * POST endpoints that should bypass idempotency enforcement.
     * Token refresh and logout are already safe to repeat.
     */
    private const EXEMPT_SUFFIXES = [
        'auth/logout',
        'auth/refresh',
    ];

    /**
     * Enforce idempotency on every POST request that is not exempt.
     *
     * Flow:
     *   1. Non-POST  → pass through unchanged.
     *   2. Exempt    → pass through unchanged.
     *   3. No header → 422.
     *   4. Key seen  → return the cached response with X-Idempotent-Replayed: true.
     *   5. In-flight → 409 (another request with same key is being processed).
     *   6. New key   → process, cache the result for KEY_TTL seconds, return it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method() !== 'POST') {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        $rawKey = $request->header(self::HEADER);

        if (empty($rawKey)) {
            return $this->errorResponse(
                'An Idempotency-Key header is required for POST requests to prevent duplicate submissions. ' .
                'Generate a unique UUID per request and reuse it only when retrying the same operation.',
                422,
                ['idempotency_key' => 'The Idempotency-Key header is missing.']
            );
        }

        if (mb_strlen($rawKey) > self::MAX_KEY_LENGTH) {
            return $this->errorResponse(
                'The Idempotency-Key must not exceed ' . self::MAX_KEY_LENGTH . ' characters.',
                422,
                ['idempotency_key' => 'Idempotency-Key is too long.']
            );
        }

        $cacheKey = $this->buildCacheKey($request, $rawKey);
        $lockKey  = "lock:{$cacheKey}";

        // ── Replay: identical key seen before ─────────────────────────────
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return response()
                ->json($cached['body'], $cached['status'])
                ->header('X-Idempotent-Replayed', 'true')
                ->header(self::HEADER, $rawKey);
        }

        // ── Concurrent duplicate: same key is still being processed ───────
        if (Cache::has($lockKey)) {
            return $this->errorResponse(
                'A request with this Idempotency-Key is already being processed. Please retry in a moment.',
                409,
                ['idempotency_key' => 'Request is currently in-flight.']
            );
        }

        // ── First execution: lock, process, cache, unlock ─────────────────
        Cache::put($lockKey, true, self::LOCK_TTL);

        try {
            $response = $next($request);

            // Cache all non-5xx outcomes (including 4xx client errors),
            // so retries with the same key get the same deterministic result.
            if ($response->getStatusCode() < 500) {
                Cache::put($cacheKey, [
                    'body'   => json_decode($response->getContent(), true),
                    'status' => $response->getStatusCode(),
                ], self::KEY_TTL);
            }

            return $response->header(self::HEADER, $rawKey);
        } finally {
            Cache::forget($lockKey);
        }
    }

    /**
     * Scoped per user (or IP for anonymous callers) + request path + client key.
     * This means two different users can legitimately reuse the same key string.
     */
    private function buildCacheKey(Request $request, string $key): string
    {
        $scope = $request->user()?->id ?? $request->ip();
        $path  = $request->path();

        return "idempotency:{$scope}:{$path}:{$key}";
    }

    private function isExempt(Request $request): bool
    {
        $path = $request->path();

        foreach (self::EXEMPT_SUFFIXES as $suffix) {
            if (str_ends_with($path, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
