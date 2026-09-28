<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforces the single-ministry rule at the database level.
 *
 * A partial unique index on a constant value means INSERT will fail
 * for any row beyond the first, making it impossible to accidentally
 * create a second ministry via raw SQL or a migration run.
 *
 * The application-level Ministry::current() / Ministry::currentId()
 * helpers provide the clean API; this migration is the safety net.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add a sentinel column that is always 1 — then make it unique.
        // MySQL / MariaDB approach (works without partial indexes):
        Schema::table('ministries', function (Blueprint $table) {
            $table->tinyInteger('singleton_lock')->default(1)->after('is_active');
        });

        DB::statement('ALTER TABLE ministries ADD CONSTRAINT ministries_singleton UNIQUE (singleton_lock)');
    }

    public function down(): void
    {
        Schema::table('ministries', function (Blueprint $table) {
            $table->dropUnique('ministries_singleton');
            $table->dropColumn('singleton_lock');
        });
    }
};
