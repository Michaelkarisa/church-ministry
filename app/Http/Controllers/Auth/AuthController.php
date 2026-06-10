<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)
            ->with('role')
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Invalid credentials. Please check your email and password.', 401);
        }

        if (! $user->is_active) {
            return $this->errorResponse('Your account has been deactivated. Contact your administrator.', 403);
        }

        // Revoke all existing tokens for this user (single-session per user)
        $user->tokens()->delete();

        $expiryMinutes = (int) config('sanctum.token_expiry', 1440);
        $expiration    = now()->addMinutes($expiryMinutes);

        $token = $user->createToken('api-token', ['*'], $expiration)->plainTextToken;

        $user->update(['last_login_at' => now()]);

        // Log the login in the background
        \App\Jobs\LogActivityJob::dispatch([
            'user_id'      => $user->id,
            'ministry_id'  => \App\Models\Ministry::currentId(),
            'zone_id'      => $user->zone_id,
            'church_id'    => $user->church_id,
            'action'       => 'login',
            'module'       => 'auth',
            'description'  => "User {$user->email} logged in.",
            'ip_address'   => $request->ip(),
            'user_agent'   => substr($request->userAgent() ?? '', 0, 500),
            'method'       => 'POST',
            'url'          => $request->fullUrl(),
            'status_code'  => 200,
            'performed_at' => now(),
        ]);

        return $this->successResponse([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_at'   => $expiration->toIso8601String(),
            'user'         => $this->userPayload($user),
        ], 'Login successful.');
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        \App\Jobs\LogActivityJob::dispatch([
            'user_id'      => $user->id,
            'ministry_id'  => \App\Models\Ministry::currentId(),
            'zone_id'      => $user->zone_id,
            'church_id'    => $user->church_id,
            'action'       => 'logout',
            'module'       => 'auth',
            'description'  => "User {$user->email} logged out.",
            'ip_address'   => $request->ip(),
            'user_agent'   => substr($request->userAgent() ?? '', 0, 500),
            'method'       => 'POST',
            'url'          => $request->fullUrl(),
            'status_code'  => 200,
            'performed_at' => now(),
        ]);

        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logged out successfully.');
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['role', 'ministry', 'zone', 'church']);
        return $this->successResponse($this->userPayload($user, true));
    }

    /**
     * PUT /api/auth/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name'  => ['sometimes', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();
        $user->update($request->only(['name', 'phone']));

        return $this->successResponse($this->userPayload($user->fresh()->load('role')), 'Profile updated.');
    }

    /**
     * PUT /api/auth/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required'],
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('Current password is incorrect.', 422);
        }

        $user->update(['password' => $request->password]);

        // Revoke all tokens — force re-login
        $user->tokens()->delete();

        return $this->successResponse(null, 'Password changed successfully. Please log in again.');
    }

    /**
     * POST /api/auth/refresh  — rotate current token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->user()->currentAccessToken()->delete();

        $expiryMinutes = (int) config('sanctum.token_expiry', 1440);
        $expiration    = now()->addMinutes($expiryMinutes);
        $token         = $user->createToken('api-token', ['*'], $expiration)->plainTextToken;

        return $this->successResponse([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_at'   => $expiration->toIso8601String(),
        ], 'Token refreshed.');
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function userPayload(User $user, bool $detailed = false): array
    {
        $base = [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'avatar'        => $user->avatar,
            'is_active'     => $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'role'          => [
                'id'           => $user->role?->id,
                'name'         => $user->role?->name,
                'display_name' => $user->role?->display_name,
                'level'        => $user->role?->level,
            ],
            'access_scope'  => $user->accessScope(),
        ];

        if ($detailed) {
            // Ministry is always the same single record — expose it once for context
            $base['ministry'] = \App\Models\Ministry::current()->only(['id', 'name', 'currency_code']);
            $base['zone']     = $user->zone   ? $user->zone->only(['id', 'name', 'code'])   : null;
            $base['church']   = $user->church ? $user->church->only(['id', 'name', 'code']) : null;
        }

        return $base;
    }
}
