<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(public readonly array $data) {}

    public function handle(): void
    {
        ActivityLog::create($this->data);
    }

    public function failed(\Throwable $e): void
    {
        // Silently discard — logging failures must never surface to users
        logger()->error('LogActivityJob failed: ' . $e->getMessage(), $this->data);
    }
}
