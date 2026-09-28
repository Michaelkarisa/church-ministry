<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A leader record belonging to a specific church (Pastor, Assistant
 * Pastor, Elder, etc). A church can have any number of these — this
 * is intentionally a table rather than a single "pastor" field.
 *
 * Not linked to Member, since Members is currently dormant.
 */
class Leadership extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'church_id', 'name', 'role', 'phone', 'email',
        'is_primary', 'is_active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active'  => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
