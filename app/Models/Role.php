<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'display_name', 'description', 'level'];

    // Role name constants — ordered to match the org hierarchy exactly:
    // Ministry -> Region -> Zone -> Sub-zone -> Church. Each admin is
    // restricted to their own level of the hierarchy and everything
    // beneath it (see User::accessScope() / applyScope()).
    const MINISTRY_ADMIN  = 'ministry_admin';
    const REGION_ADMIN    = 'region_admin';
    const ZONE_ADMIN      = 'zone_admin';
    const SUB_ZONE_ADMIN  = 'sub_zone_admin';
    const CHURCH_ADMIN    = 'church_admin';

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('name', $permission)->exists();
    }

    /** Returns true if this role's level is <= the given role level (i.e., has equal or higher authority) */
    public function isAtLeast(string $roleName): bool
    {
        return $this->level <= (self::LEVELS[$roleName] ?? 99);
    }

    /** Canonical level map — lower number = broader authority. */
    const LEVELS = [
        self::MINISTRY_ADMIN => 1,
        self::REGION_ADMIN   => 2,
        self::ZONE_ADMIN     => 3,
        self::SUB_ZONE_ADMIN => 4,
        self::CHURCH_ADMIN   => 5,
    ];
}
