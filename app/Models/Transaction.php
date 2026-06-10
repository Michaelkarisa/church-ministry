<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'church_id', 'transaction_type_id', 'recorded_by', 'member_id',
        'amount', 'currency', 'transaction_date', 'service_type',
        'reference_number', 'description', 'notes',
        'is_verified', 'verified_by', 'verified_at',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'transaction_date' => 'date',
        'is_verified'      => 'boolean',
        'verified_at'      => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeForPeriod($query, ?string $from = null, ?string $to = null, int $days = 30)
    {
        if ($from && $to) {
            return $query->whereBetween('transaction_date', [$from, $to]);
        }

        return $query->where('transaction_date', '>=', now()->subDays($days)->toDateString());
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false);
    }

    public function scopeForChurch($query, string $churchId)
    {
        return $query->where('church_id', $churchId);
    }

    public function scopeForZone($query, string $zoneId)
    {
        return $query->whereHas('church', fn ($q) => $q->where('zone_id', $zoneId));
    }
}
