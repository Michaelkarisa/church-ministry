<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class PasswordResetController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/auth/forgot-password
     * Sends a password reset link to the given email address.
     */
    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return $this->successResponse(null, 'Password reset link has been sent to your email.');
        }

        // Return a generic message even on failure to prevent email enumeration
        return $this->successResponse(
            null,
            'If an account with that email exists, a reset link has been sent.'
        );
    }

    /**
     * POST /api/auth/reset-password
     * Resets the password using the token from the reset email.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'                 => ['required'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'confirmed', Rules\Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke all existing tokens
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->successResponse(null, 'Password has been reset successfully. Please log in with your new password.');
        }

        return $this->errorResponse(
            match ($status) {
                Password::INVALID_TOKEN => 'This password reset token is invalid or has expired.',
                Password::INVALID_USER  => 'We could not find a user with that email address.',
                default                 => 'Unable to reset password. Please try again.',
            },
            422
        );
    }

    /**
     * GET /api/auth/reset-password/verify
     * Verifies whether a reset token is still valid (useful for frontend).
     */
    public function verifyToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record || ! Hash::check($request->token, $record->token)) {
            return $this->errorResponse('This reset token is invalid or has expired.', 422);
        }

        if (now()->subHour()->isAfter($record->created_at)) {
            return $this->errorResponse('This reset token has expired. Please request a new one.', 422);
        }

        return $this->successResponse(null, 'Token is valid.');
    }
}
