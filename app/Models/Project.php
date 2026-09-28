<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A church project (e.g. a building project). end_date is optional —
 * duration is always computed, never entered manually.
 */
class Project extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'church_id', 'title', 'description',
        'start_date', 'end_date', 'created_by', 'is_active',
    ];

    /** Computed duration fields included in every API response. */
    protected $appends = ['duration_label', 'days_elapsed', 'days_planned'];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Contributions (Transactions) tagged to this project. */
    public function contributions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'project_id');
    }

    // ---------------------------------------------------------------
    // Duration — always computed, never stored
    // ---------------------------------------------------------------

    /**
     * Days elapsed since start_date, up to now (open-ended) or up to
     * end_date if the project has finished.
     */
    public function getDaysElapsedAttribute(): int
    {
        $end = $this->end_date && $this->end_date->isPast() ? $this->end_date : now();
        return (int) $this->start_date->diffInDays($end);
    }

    /** Total planned days, only when an end_date is set. */
    public function getDaysPlannedAttribute(): ?int
    {
        return $this->end_date ? (int) $this->start_date->diffInDays($this->end_date) : null;
    }

    /**
     * Human-readable duration string:
     *  - no end date:      "142 days ongoing"
     *  - end date, future: "42 of 180 days"
     *  - end date, passed: "180 days (completed)" or "195 days (overdue)"
     */
    public function getDurationLabelAttribute(): string
    {
        if (! $this->end_date) {
            return "{$this->days_elapsed} days ongoing";
        }

        if ($this->end_date->isFuture()) {
            $elapsed = (int) $this->start_date->diffInDays(now());
            return "{$elapsed} of {$this->days_planned} days";
        }

        $overdue = now()->isAfter($this->end_date) && $this->is_active;
        return $overdue
            ? "{$this->days_planned} days (overdue)"
            : "{$this->days_planned} days (completed)";
    }

    /** Contribution total for this project. */
    public function getContributionsTotalAttribute(): float
    {
        return (float) $this->contributions()->sum('amount');
    }
}
