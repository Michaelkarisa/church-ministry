<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base model for append-only / event records that should never be soft-deleted.
 * (AuditLog, Login, AdEvent, Scorer, Lineup, MatchView, Comment, etc.)
 */
abstract class BaseUUid extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
}
