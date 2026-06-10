<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password',
        'role_id', 'ministry_id', 'zone_id', 'church_id',
        'phone', 'avatar', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function ministry(): BelongsTo
    {
        return $this->belongsTo(Ministry::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // ---------------------------------------------------------------
    // RBAC helpers
    // ---------------------------------------------------------------

    public function hasRole(string $roleName): bool
    {
        return $this->role?->name === $roleName;
    }

    public function isMinistryAdmin(): bool
    {
        return $this->hasRole(Role::MINISTRY_ADMIN);
    }

    public function isZoneAdmin(): bool
    {
        return $this->hasRole(Role::ZONE_ADMIN);
    }

    public function isChurchAdmin(): bool
    {
        return $this->hasRole(Role::CHURCH_ADMIN);
    }

    public function isAtLeast(string $roleName): bool
    {
        return $this->role?->isAtLeast($roleName) ?? false;
    }

    /**
     * Check permission against role + direct overrides.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->directPermissions()->where('name', $permission)->exists()) {
            return true;
        }

        return $this->role?->hasPermission($permission) ?? false;
    }

    /**
     * Returns the data-access scope for this user.
     * There is always exactly one Ministry, so ministry-level scope
     * simply means "no additional filter — sees everything."
     */
    public function accessScope(): array
    {
        return match ($this->role?->name) {
            Role::MINISTRY_ADMIN => ['level' => 'ministry'],
            Role::ZONE_ADMIN     => ['level' => 'zone',   'zone_id'   => $this->zone_id],
            default              => ['level' => 'church', 'church_id' => $this->church_id],
        };
    }

    /**
     * Applies a scoped WHERE clause to any query that carries church_id.
     * Ministry admin → unrestricted.
     * Zone admin     → restricts to churches belonging to their zone.
     * Church admin   → restricts to their single church.
     */
    public function applyScope($query, string $churchColumn = 'church_id')
    {
        $scope = $this->accessScope();

        return match ($scope['level']) {
            'ministry' => $query,
            'zone'     => $query->whereHas('church', fn ($q) =>
                              $q->where('zone_id', $scope['zone_id'])
                          ),
            default    => $query->where($churchColumn, $scope['church_id']),
        };
    }
}
