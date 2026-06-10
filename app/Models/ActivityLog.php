<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model 
{
     use HasUuids;
    protected $fillable = [
        'user_id', 'ministry_id', 'zone_id', 'church_id',
        'action', 'module', 'record_type', 'record_id',
        'description', 'old_values', 'new_values',
        'ip_address', 'user_agent', 'method', 'url',
        'status_code', 'performed_at',
    ];

    protected $casts = [
        'old_values'   => 'array',
        'new_values'   => 'array',
        'performed_at' => 'datetime',
    ];

    // ActivityLogs are never updated — immutable audit trail
    public $timestamps = true;
    const UPDATED_AT = null;

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ministry(): BelongsTo
    {
        return $this->belongsTo(Ministry::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * Static helper to create a log entry.
     * ministry_id is always resolved from the single Ministry record.
     */
    public static function record(
        User $user,
        string $action,
        string $module,
        ?string $description = null,
        array $extra = []
    ): void {
        \App\Jobs\LogActivityJob::dispatch(array_merge([
            'user_id'      => $user->id,
            'ministry_id'  => \App\Models\Ministry::currentId(),
            'zone_id'      => $user->zone_id,
            'church_id'    => $user->church_id,
            'action'       => $action,
            'module'       => $module,
            'description'  => $description,
            'ip_address'   => request()->ip(),
            'user_agent'   => substr(request()->userAgent() ?? '', 0, 500),
            'method'       => request()->method(),
            'url'          => request()->fullUrl(),
            'performed_at' => now(),
        ], $extra));
    }
}
