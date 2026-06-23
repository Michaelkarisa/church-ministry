<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;

class PasswordResetController extends Controller
{
    use ApiResponse;

    public function __construct(private PasswordResetService $passwordResetService) {}

    /**
     * POST /api/auth/forgot-password
     */
    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $message = $this->passwordResetService->sendResetLink($request->email);

        return $this->successResponse(null, $message);
    }

    /**
     * POST /api/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        try {
            $message = $this->passwordResetService->resetPassword(
                $request->email,
                $request->password,
                $request->password_confirmation,
                $request->token,
            );

            return $this->successResponse(null, $message);
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * GET /api/auth/reset-password/verify
     */
    public function verifyToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
        ]);

        try {
            $this->passwordResetService->verifyToken($request->email, $request->token);
            return $this->successResponse(null, 'Token is valid.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}
