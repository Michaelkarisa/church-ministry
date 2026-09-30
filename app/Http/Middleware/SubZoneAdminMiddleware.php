<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubZoneAdminMiddleware
{
    use ApiResponse;

    /**
     * Allows MinistryAdmin, RegionAdmin, ZoneAdmin and SubZoneAdmin
     * (level 1-4) — everyone except a plain Church Administrator.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if (! $user->is_active) {
            return $this->errorResponse('Your account has been deactivated.', 403);
        }

        if (! $user->isAtLeast(Role::SUB_ZONE_ADMIN)) {
            return $this->errorResponse(
                'Access denied. Sub-zone Administrator privileges or above required.',
                403
            );
        }

        return $next($request);
    }
}
