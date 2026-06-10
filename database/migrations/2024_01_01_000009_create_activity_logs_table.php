<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('ministry_id')->nullable()->constrained('ministries')->nullOnDelete();
            $table->foreignUuid('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignUuid('church_id')->nullable()->constrained('churches')->nullOnDelete();

            $table->string('action', 50);
            $table->string('module', 80);
            $table->string('record_type', 80)->nullable();
            $table->uuid('record_id')->nullable();       // UUID — points to any UUID-keyed record
            $table->string('description', 500)->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('url', 500)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();

            $table->timestamp('performed_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'performed_at']);
            $table->index(['module', 'action']);
            $table->index(['church_id', 'performed_at']);
            $table->index(['zone_id', 'performed_at']);
            $table->index(['ministry_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
