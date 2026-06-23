<?php

namespace App\Services;

use App\Jobs\LogActivityJob;
use App\Models\Ministry;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    // ---------------------------------------------------------------
    // Authentication
    // ---------------------------------------------------------------

    /**
     * Validate credentials and issue a new token.
     * Throws \DomainException on invalid credentials (401) or inactive account (403).
     */
    public function login(string $email, string $password, string $ip, string $userAgent, string $url): array
    {
        $user = User::where('email', $email)->with('role')->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new \DomainException('Invalid credentials. Please check your email and password.', 401);
        }

        if (! $user->is_active) {
            throw new \DomainException('Your account has been deactivated. Contact your administrator.', 403);
        }

        // Revoke all existing tokens (single-session per user)
        $user->tokens()->delete();

        $tokenData = $this->issueToken($user);

        $user->update(['last_login_at' => now()]);

        LogActivityJob::dispatch([
            'user_id'      => $user->id,
            'ministry_id'  => Ministry::currentId(),
            'zone_id'      => $user->zone_id,
            'church_id'    => $user->church_id,
            'action'       => 'login',
            'module'       => 'auth',
            'description'  => "User {$user->email} logged in.",
            'ip_address'   => $ip,
            'user_agent'   => substr($userAgent, 0, 500),
            'method'       => 'POST',
            'url'          => $url,
            'status_code'  => 200,
            'performed_at' => now(),
        ]);

        return array_merge($tokenData, ['user' => $this->buildUserPayload($user)]);
    }

    /**
     * Revoke the current token and dispatch a logout activity log.
     */
    public function logout(User $user, string $ip, string $userAgent, string $url): void
    {
        LogActivityJob::dispatch([
            'user_id'      => $user->id,
            'ministry_id'  => Ministry::currentId(),
            'zone_id'      => $user->zone_id,
            'church_id'    => $user->church_id,
            'action'       => 'logout',
            'module'       => 'auth',
            'description'  => "User {$user->email} logged out.",
            'ip_address'   => $ip,
            'user_agent'   => substr($userAgent, 0, 500),
            'method'       => 'POST',
            'url'          => $url,
            'status_code'  => 200,
            'performed_at' => now(),
        ]);

        $user->currentAccessToken()->delete();
    }

    /**
     * Return the authenticated user with all related resources loaded.
     */
    public function getProfile(User $user): array
    {
        $user->load(['role', 'ministry', 'zone', 'church']);
        return $this->buildUserPayload($user, detailed: true);
    }

    /**
     * Update name / phone profile fields.
     */
    public function updateProfile(User $user, array $data): array
    {
        $user->update($data);
        return $this->buildUserPayload($user->fresh()->load('role'));
    }

    /**
     * Change the user's password and revoke all tokens.
     * Throws \DomainException(422) when the current password is wrong.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new \DomainException('Current password is incorrect.', 422);
        }

        $user->update(['password' => $newPassword]);
        $user->tokens()->delete();
    }

    /**
     * Rotate the current token and return fresh token data.
     */
    public function refreshToken(User $user): array
    {
        $user->currentAccessToken()->delete();
        return $this->issueToken($user);
    }

    // ---------------------------------------------------------------
    // Payload helpers
    // ---------------------------------------------------------------

    public function buildUserPayload(User $user, bool $detailed = false): array
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
            $base['ministry'] = Ministry::current()->only(['id', 'name', 'currency_code']);
            $base['zone']     = $user->zone   ? $user->zone->only(['id', 'name', 'code'])   : null;
            $base['church']   = $user->church ? $user->church->only(['id', 'name', 'code']) : null;
        }

        return $base;
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function issueToken(User $user): array
    {
        $expiryMinutes = (int) config('sanctum.token_expiry', 1440);
        $expiration    = now()->addMinutes($expiryMinutes);
        $token         = $user->createToken('api-token', ['*'], $expiration)->plainTextToken;

        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_at'   => $expiration->toIso8601String(),
        ];
    }
}
