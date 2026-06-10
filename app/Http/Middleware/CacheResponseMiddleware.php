<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponseMiddleware
{
    /**
     * Cache GET responses.
     * Usage: ->middleware('cache.response')
     *        ->middleware('cache.response:300')   (custom TTL in seconds)
     */
    public function handle(Request $request, Closure $next, int $ttl = 0): Response
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        $ttl = $ttl > 0 ? $ttl : (int) config('church.analytics.cache_ttl', 3600);

        $cacheKey = $this->buildCacheKey($request);

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return response()->json($cached)
                ->header('X-Cache', 'HIT')
                ->header('X-Cache-Key', md5($cacheKey));
        }

        $response = $next($request);

        // Only cache successful responses
        if ($response->getStatusCode() === 200) {
            $content = json_decode($response->getContent(), true);
            Cache::put($cacheKey, $content, $ttl);

            $response->headers->set('X-Cache', 'MISS');
            $response->headers->set('X-Cache-TTL', (string) $ttl);
        }

        return $response;
    }

    private function buildCacheKey(Request $request): string
    {
        $userId  = $request->user()?->id ?? 'guest';
        $path    = $request->path();
        $query   = http_build_query($request->query());

        return "response_cache:{$userId}:{$path}:{$query}";
    }

    /**
     * Call this from controllers when data changes to bust scoped caches.
     */
    public static function bust(string $pattern): void
    {
        // Works with file/redis cache drivers that support tags or prefix scan
        Cache::forget($pattern);
    }
}
