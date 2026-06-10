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
        'zone_id', 'name', 'code', 'address', 'location',
        'phone', 'email', 'pastor_name', 'establishment_date',
        'latitude', 'longitude', 'is_active',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'establishment_date' => 'date',
        'latitude'           => 'float',
        'longitude'          => 'float',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
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
    // Accessors
    // ---------------------------------------------------------------

    public function getMembersCountAttribute(): int
    {
        return $this->members()->where('is_active', true)->count();
    }
}
