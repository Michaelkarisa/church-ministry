<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('church_id')->constrained()->cascadeOnDelete();

            $table->string('name', 150);
            // Free-text role (e.g. "Pastor", "Assistant Pastor", "Elder") —
            // not a fixed dropdown, so churches can label roles as they use them.
            $table->string('role', 100);
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();

            // Marks the primary/senior leader shown by default for the church
            // (e.g. the Pastor), without forcing a single fixed "pastor" field.
            $table->boolean('is_primary')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderships');
    }
};
