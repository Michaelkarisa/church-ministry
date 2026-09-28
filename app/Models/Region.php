<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Region extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'ministry_id', 'name', 'code',
        'address', 'phone', 'email', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function ministry(): BelongsTo
    {
        return $this->belongsTo(Ministry::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function subZones(): HasManyThrough
    {
        return $this->hasManyThrough(SubZone::class, Zone::class);
    }

    /**
     * All churches under this region, reached through Zone -> Sub-zone.
     * (hasManyThrough only supports one intermediate table, so the
     * Zone -> Sub-zone -> Church hop is expressed as a scoped query.)
     */
    public function churches()
    {
        return Church::whereHas('subZone.zone', fn ($q) => $q->where('region_id', $this->id));
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getZonesCountAttribute(): int
    {
        return (int) ($this->attributes['zones_count'] ?? $this->zones()->count());
    }

    public function getChurchesCountAttribute(): int
    {
        return (int) ($this->attributes['churches_count'] ?? $this->churches()->count());
    }
}
