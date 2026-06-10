<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function churches(): HasManyThrough
    {
        return $this->hasManyThrough(Church::class, Zone::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getChurchesCountAttribute(): int
    {
        return $this->churches()->count();
    }

    public function getZonesCountAttribute(): int
    {
        return $this->zones()->count();
    }
}
