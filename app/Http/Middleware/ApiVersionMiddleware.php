<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiVersionMiddleware
{
    use ApiResponse;

    /** All versions the application will accept. */
    private const SUPPORTED_VERSIONS = ['v1'];

    /** The version new clients should be targeting. */
    private const CURRENT_VERSION = 'v1';

    /**
     * Versions that still work but are end-of-life.
     * Populate this when a new major version ships, e.g. ['v1'] once v2 launches.
     */
    private const DEPRECATED_VERSIONS = [];

    /**
     * Usage in routes:  ->middleware('api.version:v1')
     *
     * • Rejects unsupported version strings with 404 so clients fail loudly.
     * • Stamps every response with:
     *     X-API-Version         – the version this group was registered under
     *     X-API-Current-Version – latest stable version (nudges old clients to upgrade)
     * • For deprecated versions adds:
     *     X-API-Deprecated              – "true"
     *     X-API-Deprecation-Warning     – human-readable sunset message
     */
    public function handle(Request $request, Closure $next, string $version = 'v1'): Response
    {
        if (! in_array($version, self::SUPPORTED_VERSIONS, true)) {
            return $this->errorResponse(
                "API version '{$version}' is not supported. " .
                'Supported versions: ' . implode(', ', self::SUPPORTED_VERSIONS) . '.',
                404,
                ['supported_versions' => self::SUPPORTED_VERSIONS]
            );
        }

        $response = $next($request);

        $response->headers->set('X-API-Version',         $version);
        $response->headers->set('X-API-Current-Version', self::CURRENT_VERSION);

        if (in_array($version, self::DEPRECATED_VERSIONS, true)) {
            $response->headers->set('X-API-Deprecated', 'true');
            $response->headers->set(
                'X-API-Deprecation-Warning',
                "Version {$version} is deprecated and will be removed in a future release. " .
                'Please migrate to ' . self::CURRENT_VERSION . '.'
            );
        }

        return $response;
    }
}
