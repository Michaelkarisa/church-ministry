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
        'role_id', 'ministry_id', 'region_id', 'zone_id', 'sub_zone_id', 'church_id',
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

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function subZone(): BelongsTo
    {
        return $this->belongsTo(SubZone::class);
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

    public function isRegionAdmin(): bool
    {
        return $this->hasRole(Role::REGION_ADMIN);
    }

    public function isZoneAdmin(): bool
    {
        return $this->hasRole(Role::ZONE_ADMIN);
    }

    public function isSubZoneAdmin(): bool
    {
        return $this->hasRole(Role::SUB_ZONE_ADMIN);
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
     * Resolve which region/zone/sub-zone this user's own assignment
     * falls under, regardless of which tier their role sits at. Used
     * to check "is this user inside my branch of the hierarchy?" when
     * the user being checked isn't necessarily a Church Administrator
     * (e.g. a Zone Admin checking whether a Sub-zone Admin reports up
     * through their zone).
     */
    public function resolvedRegionId(): ?string
    {
        return match ($this->role?->name) {
            Role::REGION_ADMIN   => $this->region_id,
            Role::ZONE_ADMIN     => $this->zone?->region_id,
            Role::SUB_ZONE_ADMIN => $this->subZone?->zone?->region_id,
            Role::CHURCH_ADMIN   => $this->church?->region_id,
            default              => null,
        };
    }

    public function resolvedZoneId(): ?string
    {
        return match ($this->role?->name) {
            Role::ZONE_ADMIN     => $this->zone_id,
            Role::SUB_ZONE_ADMIN => $this->subZone?->zone_id,
            Role::CHURCH_ADMIN   => $this->church?->zone_id,
            default              => null,
        };
    }

    public function resolvedSubZoneId(): ?string
    {
        return match ($this->role?->name) {
            Role::SUB_ZONE_ADMIN => $this->sub_zone_id,
            Role::CHURCH_ADMIN   => $this->church?->sub_zone_id,
            default              => null,
        };
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
     * Returns the data-access scope for this user, one entry per level
     * of the hierarchy (Ministry -> Region -> Zone -> Sub-zone ->
     * Church). There is always exactly one Ministry, so ministry-level
     * scope simply means "no additional filter — sees everything."
     */
    public function accessScope(): array
    {
        return match ($this->role?->name) {
            Role::MINISTRY_ADMIN => ['level' => 'ministry'],
            Role::REGION_ADMIN   => ['level' => 'region',   'region_id'   => $this->region_id],
            Role::ZONE_ADMIN     => ['level' => 'zone',     'zone_id'     => $this->zone_id],
            Role::SUB_ZONE_ADMIN => ['level' => 'sub_zone', 'sub_zone_id' => $this->sub_zone_id],
            default              => ['level' => 'church',   'church_id'   => $this->church_id],
        };
    }

    /**
     * Applies a scoped WHERE clause to any query that carries a
     * `church_id` column (directly, or via a `church` relation when
     * $viaRelation is true — see below).
     *
     *   Ministry admin  -> unrestricted
     *   Region admin    -> churches under any zone/sub-zone in their region
     *   Zone admin      -> churches under any sub-zone in their zone
     *   Sub-zone admin  -> churches directly in their sub-zone
     *   Church admin    -> their single church
     *
     * $churchColumn is the column name to filter on when the scope
     * bottoms out at "church" (the model itself has church_id, e.g.
     * Transaction, Member). For models one hop away from Church (e.g.
     * you're scoping a query on Church itself), see
     * Church::scopeVisibleTo() instead, which mirrors this logic
     * without assuming a `church_id` column exists.
     */
    public function applyScope($query, string $churchColumn = 'church_id')
    {
        $scope = $this->accessScope();

        return match ($scope['level']) {
            'ministry' => $query,
            'region'   => $query->whereHas('church.subZone.zone', fn ($q) =>
                              $q->where('region_id', $scope['region_id'])
                          ),
            'zone'     => $query->whereHas('church.subZone', fn ($q) =>
                              $q->where('zone_id', $scope['zone_id'])
                          ),
            'sub_zone' => $query->whereHas('church', fn ($q) =>
                              $q->where('sub_zone_id', $scope['sub_zone_id'])
                          ),
            default    => $query->where($churchColumn, $scope['church_id']),
        };
    }
}
