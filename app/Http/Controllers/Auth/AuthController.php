<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private AuthService $authService) {}

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->login(
                $request->email,
                $request->password,
                $request->ip(),
                $request->userAgent() ?? '',
                $request->fullUrl(),
            );

            return $this->successResponse($data, 'Login successful.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 401);
        }
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout(
            $request->user(),
            $request->ip(),
            $request->userAgent() ?? '',
            $request->fullUrl(),
        );

        return $this->successResponse(null, 'Logged out successfully.');
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->authService->getProfile($request->user())
        );
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

        $payload = $this->authService->updateProfile(
            $request->user(),
            $request->only(['name', 'phone']),
        );

        return $this->successResponse($payload, 'Profile updated.');
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

        try {
            $this->authService->changePassword(
                $request->user(),
                $request->current_password,
                $request->password,
            );

            return $this->successResponse(null, 'Password changed successfully. Please log in again.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * POST /api/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $tokenData = $this->authService->refreshToken($request->user());

        return $this->successResponse($tokenData, 'Token refreshed.');
    }
}
