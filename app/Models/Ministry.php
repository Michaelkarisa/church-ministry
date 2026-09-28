<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class Ministry extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'address', 'city', 'county', 'country',
        'phone', 'email', 'logo', 'description',
        'currency_code', 'founded_year', 'website', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Single-ministry accessor
    // ---------------------------------------------------------------

    /**
     * Always returns the one Ministry record.
     * Throws if none has been seeded yet.
     */
    public static function current(): self
    {
        return static::firstOrFail();
    }

    /**
     * Returns the ID of the single Ministry.
     * Cached for the request lifetime so we never query twice.
     */
    public static function currentId(): string
    {
        static $id = null;
        return $id ??= static::current()->id;
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * All churches ministry-wide, reached through
     * Region -> Zone -> Sub-zone (a 3-hop chain, so expressed as a
     * scoped query rather than hasManyThrough, which only supports
     * one intermediate table).
     */
    public function churches()
    {
        return Church::whereHas('subZone.zone.region', fn ($q) => $q->where('ministry_id', $this->id));
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getRegionsCountAttribute(): int
    {
        return $this->regions()->count();
    }

    public function getZonesCountAttribute(): int
    {
        return Zone::whereHas('region', fn ($q) => $q->where('ministry_id', $this->id))->count();
    }

    public function getSubZonesCountAttribute(): int
    {
        return SubZone::whereHas('zone.region', fn ($q) => $q->where('ministry_id', $this->id))->count();
    }

    public function getChurchesCountAttribute(): int
    {
        return $this->churches()->count();
    }
}
