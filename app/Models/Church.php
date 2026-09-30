<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Church extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'sub_zone_id', 'name', 'code', 'address', 'location',
        'phone', 'email', 'establishment_date',
        'latitude', 'longitude',
        'land_status', 'building_status',
        'is_active',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'establishment_date' => 'date',
        'latitude'           => 'float',
        'longitude'          => 'float',
    ];

    public const LAND_STATUSES = ['rented', 'bought'];
    public const BUILDING_STATUSES = ['rented', 'built', 'under_construction'];

    // ---------------------------------------------------------------
    // Core hierarchy relationships
    // ---------------------------------------------------------------

    public function subZone(): BelongsTo
    {
        return $this->belongsTo(SubZone::class);
    }

    /**
     * Scope a Church query to what the given user is allowed to see,
     * mirroring User::applyScope() but for querying Church itself
     * (which has no church_id column to filter on).
     *
     *   Ministry admin  -> unrestricted
     *   Region admin    -> churches under any zone/sub-zone in their region
     *   Zone admin      -> churches under any sub-zone in their zone
     *   Sub-zone admin  -> churches directly in their sub-zone
     *   Church admin    -> their single church
     */
    public function scopeVisibleTo($query, User $user)
    {
        $scope = $user->accessScope();

        return match ($scope['level']) {
            'ministry' => $query,
            'region'   => $query->whereHas('subZone.zone', fn ($q) =>
                              $q->where('region_id', $scope['region_id'])
                          ),
            'zone'     => $query->whereHas('subZone', fn ($q) =>
                              $q->where('zone_id', $scope['zone_id'])
                          ),
            'sub_zone' => $query->where('sub_zone_id', $scope['sub_zone_id']),
            default    => $query->where('id', $scope['church_id']),
        };
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ---------------------------------------------------------------
    // Church-entity relationships (new)
    // ---------------------------------------------------------------

    public function leadership(): HasMany
    {
        return $this->hasMany(Leadership::class);
    }

    /** The single leader flagged as primary (e.g. the Pastor), if any. */
    public function primaryLeader(): HasMany
    {
        return $this->leadership()->where('is_primary', true);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    // ---------------------------------------------------------------
    // Backward-compatible hierarchy accessors
    // ---------------------------------------------------------------
    // Churches used to belong directly to a Zone. They now belong to a
    // Sub-zone, which belongs to a Zone. These accessors let existing
    // code (scoping, filters, seeders) keep reading `$church->zone`
    // and `$church->zone_id` without every call site knowing about the
    // extra hop.

    public function zone(): ?Zone
    {
        return $this->subZone?->zone;
    }

    public function getZoneIdAttribute(): ?string
    {
        return $this->subZone?->zone_id;
    }

    public function region(): ?Region
    {
        return $this->subZone?->zone?->region;
    }

    public function getRegionIdAttribute(): ?string
    {
        return $this->subZone?->zone?->region_id;
    }

    public function getMinistryIdAttribute(): ?string
    {
        return $this->subZone?->zone?->region?->ministry_id;
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getMembersCountAttribute(): int
    {
        return $this->members()->where('is_active', true)->count();
    }
}
