<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MinistryAdminMiddleware
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if (! $user->is_active) {
            return $this->errorResponse('Your account has been deactivated.', 403);
        }

        if (! $user->isMinistryAdmin()) {
            return $this->errorResponse(
                'Access denied. Ministry Administrator privileges required.',
                403
            );
        }

        return $next($request);
    }
}
