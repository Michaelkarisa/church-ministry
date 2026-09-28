<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('church_id')->constrained()->cascadeOnDelete();

            // Free-text event type (e.g. "Sunday Service", "Outreach") —
            // not a fixed dropdown, per the agreed design.
            $table->string('type', 150);
            $table->date('event_date');
            $table->unsignedInteger('attendance_count')->default(0);

            // Sermon detail is optional — not every event has one.
            $table->string('sermon_topic', 255)->nullable();
            $table->string('sermon_speaker', 150)->nullable();

            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
