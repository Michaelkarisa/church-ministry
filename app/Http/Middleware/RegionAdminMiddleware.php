<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegionAdminMiddleware
{
    use ApiResponse;

    /**
     * Allows MinistryAdmin and RegionAdmin (level 1 & 2).
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

        if (! $user->isAtLeast(Role::REGION_ADMIN)) {
            return $this->errorResponse(
                'Access denied. Region Administrator privileges or above required.',
                403
            );
        }

        return $next($request);
    }
}
