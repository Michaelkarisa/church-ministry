<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChurchAdminMiddleware
{
    use ApiResponse;

    /**
     * Allows any authenticated admin (all three roles).
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

        if (! $user->isAtLeast(Role::CHURCH_ADMIN)) {
            return $this->errorResponse('Access denied. Admin privileges required.', 403);
        }

        return $next($request);
    }
}
