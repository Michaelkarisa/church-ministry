<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A church activity record — e.g. a Sunday Service. Captures type
 * (free text), date, attendance, an optional sermon, and any
 * contributions (Transactions) tagged to it.
 */
class Event extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'church_id', 'type', 'event_date', 'attendance_count',
        'sermon_topic', 'sermon_speaker', 'recorded_by',
    ];

    protected $casts = [
        'event_date'       => 'date',
        'attendance_count' => 'integer',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Contributions (Transactions) tagged to this event. */
    public function contributions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'event_id');
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    public function getHasSermonAttribute(): bool
    {
        return filled($this->sermon_topic) || filled($this->sermon_speaker);
    }

    /** Sum of contributions linked to this event. */
    public function getContributionsTotalAttribute(): float
    {
        return (float) $this->contributions()->sum('amount');
    }
}
