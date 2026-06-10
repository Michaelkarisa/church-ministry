<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'display_name', 'description', 'level'];

    // Role name constants
    const MINISTRY_ADMIN = 'ministry_admin';
    const ZONE_ADMIN     = 'zone_admin';
    const CHURCH_ADMIN   = 'church_admin';

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

    /** Returns true if this role's level is ≤ the given role level (i.e., has equal or higher authority) */
    public function isAtLeast(string $roleName): bool
    {
        $levels = [
            self::MINISTRY_ADMIN => 1,
            self::ZONE_ADMIN     => 2,
            self::CHURCH_ADMIN   => 3,
        ];

        return $this->level <= ($levels[$roleName] ?? 99);
    }
}
