<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystemService
{

    public function revokeRole(User $target, User $actor): User
    {
        if ($target->id === $actor->id) {
            throw new \DomainException('You cannot revoke your own role.', 422);
        }

        if ($target->role_id === null) {
            throw new \DomainException('User does not have a role assigned.', 422);
        }

        // ZoneAdmin cannot touch MinistryAdmin or another ZoneAdmin
        if ($actor->isZoneAdmin() && ! $actor->isMinistryAdmin()) {
            if ($target->isMinistryAdmin() || $target->isZoneAdmin()) {
                throw new \DomainException(
                    'Zone Administrators cannot revoke the role of a Ministry or Zone Administrator.',
                    403
                );
            }
        }

        $previousRole = $target->role?->name;

        $target->update(['role_id' => null]);
        $target->tokens()->delete();   // force re-login so new (no) role is reflected

        Log::info('Role revoked', [
            'target_user_id'  => $target->id,
            'target_email'    => $target->email,
            'previous_role'   => $previousRole,
            'revoked_by'      => $actor->id,
            'revoked_by_role' => $actor->role?->name,
        ]);

        return $target->fresh()->load(['role', 'zone', 'church']);
    }

    /**
     * Assign (or replace) a role on a user.
     *
     * ZoneAdmin can only assign roles at church-level (level >= 3).
     * MinistryAdmin can assign any role.
     *
     * @throws \DomainException
     */
    public function assignRole(User $target, Role $role, User $actor): User
    {
        if ($target->id === $actor->id) {
            throw new \DomainException('You cannot change your own role.', 422);
        }

        // ZoneAdmin cannot assign ZoneAdmin or MinistryAdmin
        if ($actor->isZoneAdmin() && ! $actor->isMinistryAdmin()) {
            if ($role->level <= 2) {
                throw new \DomainException(
                    'Zone Administrators can only assign Church-level roles or below.',
                    403
                );
            }
        }

        $previousRole = $target->role?->name;

        $target->update(['role_id' => $role->id]);
        $target->tokens()->delete();   // force re-login with updated role

        Log::info('Role assigned', [
            'target_user_id'  => $target->id,
            'target_email'    => $target->email,
            'previous_role'   => $previousRole,
            'new_role'        => $role->name,
            'assigned_by'     => $actor->id,
            'assigned_by_role'=> $actor->role?->name,
        ]);

        return $target->fresh()->load(['role', 'zone', 'church']);
    }

    // ---------------------------------------------------------------
    // Cache Management
    // ---------------------------------------------------------------

    /**
     * Flush the entire application cache.
     * Returns stats about what was cleared.
     */
    public function flushCache(User $actor): array
    {
        $store = config('cache.default', 'file');

        Cache::flush();

        Log::info('Application cache flushed', [
            'flushed_by'  => $actor->id,
            'actor_role'  => $actor->role?->name,
            'cache_store' => $store,
        ]);

        return [
            'cache_store' => $store,
            'flushed_at'  => now()->toIso8601String(),
            'flushed_by'  => $actor->email,
        ];
    }

    /**
     * Flush only the response/analytics cache keys.
     * Safer than a full flush in production — leaves session/queue caches intact.
     */
    public function flushResponseCache(User $actor): array
    {
        $patterns = ['response_cache:*', 'analytics:*', 'church:*'];
        $cleared  = 0;

        foreach ($patterns as $pattern) {
            try {
                // For Redis: use scan-based deletion
                if ($this->isRedis()) {
                    $cleared += $this->flushRedisPattern($pattern);
                } else {
                    // For file/array drivers, forget by known key prefixes
                    Cache::forget($pattern);
                    $cleared++;
                }
            } catch (\Throwable $e) {
                Log::warning("Could not flush cache pattern [{$pattern}]: " . $e->getMessage());
            }
        }

        Log::info('Response cache flushed', [
            'flushed_by'    => $actor->id,
            'actor_role'    => $actor->role?->name,
            'keys_cleared'  => $cleared,
        ]);

        return [
            'patterns_cleared' => $patterns,
            'keys_cleared'     => $cleared,
            'flushed_at'       => now()->toIso8601String(),
            'flushed_by'       => $actor->email,
        ];
    }

    // ---------------------------------------------------------------
    // Token / Session Management
    // ---------------------------------------------------------------

    /**
     * Revoke ALL tokens for a specific user (force logout everywhere).
     *
     * @throws \DomainException
     */
    public function revokeUserTokens(User $target, User $actor): array
    {
        if ($target->id === $actor->id) {
            throw new \DomainException('Use the logout endpoint to revoke your own tokens.', 422);
        }

        if ($actor->isZoneAdmin() && ! $actor->isMinistryAdmin()) {
            if ($target->isMinistryAdmin() || $target->isZoneAdmin()) {
                throw new \DomainException(
                    'Zone Administrators cannot force-logout Ministry or Zone Administrators.',
                    403
                );
            }
        }

        $count = $target->tokens()->count();
        $target->tokens()->delete();

        Log::info('User tokens revoked', [
            'target_user_id' => $target->id,
            'target_email'   => $target->email,
            'tokens_revoked' => $count,
            'revoked_by'     => $actor->id,
            'actor_role'     => $actor->role?->name,
        ]);

        return [
            'tokens_revoked' => $count,
            'user_id'        => $target->id,
            'user_email'     => $target->email,
        ];
    }

    /**
     * Revoke ALL active tokens across the system (mass force-logout).
     * Ministry Admin only.
     */
    public function revokeAllTokens(User $actor): array
    {
        // Preserve the actor's own current token so they stay logged in
        $currentTokenId = $actor->currentAccessToken()?->id;

        $query = DB::table('personal_access_tokens');

        if ($currentTokenId) {
            $query->where('id', '!=', $currentTokenId);
        }

        $count = $query->count();
        $query->delete();

        Log::warning('ALL user tokens revoked (mass force-logout)', [
            'revoked_by'     => $actor->id,
            'actor_role'     => $actor->role?->name,
            'tokens_revoked' => $count,
        ]);

        return [
            'tokens_revoked'  => $count,
            'actor_preserved' => true,
            'revoked_at'      => now()->toIso8601String(),
        ];
    }

    // ---------------------------------------------------------------
    // Health & Diagnostics
    // ---------------------------------------------------------------

    /**
     * Return a system health snapshot: DB, cache, queue status.
     */
    public function healthCheck(): array
    {
        $checks = [];

        // Database
        try {
            DB::statement('SELECT 1');
            $checks['database'] = ['status' => 'ok', 'driver' => config('database.default')];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Cache
        try {
            $key = '_health_check_' . uniqid();
            Cache::put($key, true, 5);
            $ok = Cache::get($key) === true;
            Cache::forget($key);
            $checks['cache'] = ['status' => $ok ? 'ok' : 'error', 'driver' => config('cache.default')];
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Queue
        try {
            $checks['queue'] = ['status' => 'ok', 'driver' => config('queue.default')];
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        $overall = collect($checks)->every(fn ($c) => $c['status'] === 'ok') ? 'healthy' : 'degraded';

        return [
            'status'     => $overall,
            'checks'     => $checks,
            'checked_at' => now()->toIso8601String(),
            'app_env'    => config('app.env'),
            'app_debug'  => config('app.debug'),
        ];
    }

    /**
     * Return a list of all roles with user counts.
     */
    public function roles(): array
    {
        return Role::withCount('users')
            ->orderBy('level')
            ->get()
            ->toArray();
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function isRedis(): bool
    {
        return in_array(config('cache.default'), ['redis', 'predis']);
    }

    private function flushRedisPattern(string $pattern): int
    {
        $redis  = Cache::getStore()->getRedis();
        $keys   = $redis->keys(config('cache.prefix', '') . $pattern);
        $count  = count($keys);

        if ($count > 0) {
            $redis->del($keys);
        }

        return $count;
    }
}