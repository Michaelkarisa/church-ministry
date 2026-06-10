<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'church_id', 'member_number', 'first_name', 'last_name',
        'email', 'phone', 'date_of_birth', 'gender', 'marital_status',
        'address', 'occupation', 'membership_date', 'is_active',
    ];

    protected $casts = [
        'date_of_birth'   => 'date',
        'membership_date' => 'date',
        'is_active'       => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
