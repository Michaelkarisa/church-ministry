<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActivityLoggerMiddleware
{
    // Only log mutating methods
    private const LOGGED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    // Skip these routes (e.g., token refresh, health checks)
    private const SKIP_PATTERNS = [
        'api/auth/refresh',
        'api/auth/me',
        'health',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->logIfNeeded($request, $response);
        } catch (\Throwable $e) {
            // Never let logging failure break the API response
            logger()->error('ActivityLogger failed: ' . $e->getMessage());
        }

        return $response;
    }

    private function logIfNeeded(Request $request, Response $response): void
    {
        $user = $request->user();

        if (! $user) {
            return;
        }

        if (! in_array($request->method(), self::LOGGED_METHODS)) {
            return;
        }

        foreach (self::SKIP_PATTERNS as $pattern) {
            if (str_contains($request->path(), $pattern)) {
                return;
            }
        }

        $statusCode = $response->getStatusCode();

        \App\Jobs\LogActivityJob::dispatch([
            'user_id'      => $user->id,
            'ministry_id'  => \App\Models\Ministry::currentId(),
            'zone_id'      => $user->zone_id,
            'church_id'    => $user->church_id,
            'action'       => $this->deriveAction($request->method(), $statusCode),
            'module'       => $this->deriveModule($request->path()),
            'description'  => $this->deriveDescription($request),
            'new_values'   => $this->safeRequestBody($request),
            'ip_address'   => $request->ip(),
            'user_agent'   => substr($request->userAgent() ?? '', 0, 500),
            'method'       => $request->method(),
            'url'          => $request->fullUrl(),
            'status_code'  => $statusCode,
            'performed_at' => now(),
        ]);
    }

    private function deriveAction(string $method, int $statusCode): string
    {
        if ($statusCode >= 400) {
            return 'failed_' . strtolower($method);
        }

        return match ($method) {
            'POST'   => 'created',
            'PUT',
            'PATCH'  => 'updated',
            'DELETE' => 'deleted',
            default  => strtolower($method),
        };
    }

    private function deriveModule(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        // e.g. api/transactions/5/verify → "transactions"
        $module = $segments[1] ?? $segments[0] ?? 'unknown';
        return str_replace('-', '_', $module);
    }

    private function deriveDescription(Request $request): string
    {
        $method = $request->method();
        $path   = $request->path();
        return "{$method} {$path}";
    }

    private function safeRequestBody(Request $request): ?array
    {
        $data = $request->except(['password', 'password_confirmation', 'current_password', '_token']);
        return empty($data) ? null : $data;
    }
}
