<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetService
{
    /**
     * Send a password-reset link for the given e-mail address.
     * Always returns a safe, non-enumerating message regardless of outcome.
     */
    public function sendResetLink(string $email): string
    {
        Password::sendResetLink(['email' => $email]);

        return 'If an account with that email exists, a reset link has been sent.';
    }

    /**
     * Reset the password using the supplied token.
     * Returns a success message on completion.
     * Throws \DomainException(422) when the token / user is invalid.
     */
    public function resetPassword(string $email, string $password, string $passwordConfirmation, string $token): string
    {
        $status = Password::reset(
            [
                'email'                 => $email,
                'password'              => $password,
                'password_confirmation' => $passwordConfirmation,
                'token'                 => $token,
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Force re-login on all devices
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return 'Password has been reset successfully. Please log in with your new password.';
        }

        $message = match ($status) {
            Password::INVALID_TOKEN => 'This password reset token is invalid or has expired.',
            Password::INVALID_USER  => 'We could not find a user with that email address.',
            default                 => 'Unable to reset password. Please try again.',
        };

        throw new \DomainException($message, 422);
    }

    /**
     * Verify that a reset token is still valid.
     * Throws \DomainException(422) when the token is missing or expired.
     */
    public function verifyToken(string $email, string $token): void
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $record || ! Hash::check($token, $record->token)) {
            throw new \DomainException('This reset token is invalid or has expired.', 422);
        }

        if (now()->subHour()->isAfter($record->created_at)) {
            throw new \DomainException('This reset token has expired. Please request a new one.', 422);
        }
    }
}
