<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // Transaction Types
        // ---------------------------------------------------------------
        Schema::create('transaction_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->enum('category', [
                'offering', 'tithe', 'donation', 'project',
                'building', 'welfare', 'pledge', 'harvesting',
                'thanksgiving', 'other',
            ])->default('other');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // Transactions
        // ---------------------------------------------------------------
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('church_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('transaction_type_id')->constrained('transaction_types');
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->foreignUuid('member_id')->nullable()->constrained('members')->nullOnDelete();

            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('KES');
            $table->date('transaction_date');

            $table->enum('service_type', [
                'sunday_morning', 'sunday_evening', 'wednesday',
                'friday', 'saturday', 'special', 'crusade',
                'prayer_meeting', 'conference', 'other',
            ])->nullable();

            $table->string('reference_number', 80)->nullable()->unique();
            $table->string('description', 500)->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_verified')->default(false);
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'transaction_date']);
            $table->index(['church_id', 'transaction_type_id']);
            $table->index(['transaction_date', 'is_verified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('transaction_types');
    }
};
