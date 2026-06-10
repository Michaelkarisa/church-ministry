<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'ministry_id', 'name', 'code', 'region',
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

    public function churches(): HasMany
    {
        return $this->hasMany(Church::class);
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
}
