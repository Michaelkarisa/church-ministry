<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubZone extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'zone_id', 'name', 'code',
        'address', 'phone', 'email', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function churches(): HasMany
    {
        return $this->hasMany(Church::class);
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getChurchesCountAttribute(): int
    {
        return (int) ($this->attributes['churches_count'] ?? $this->churches()->count());
    }
}
