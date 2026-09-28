<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Churches now sit under a Sub-zone (was directly under Zone).
            $table->foreignUuid('sub_zone_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30)->unique();

            // ---- General details -------------------------------------------------
            $table->text('address')->nullable();
            $table->string('location', 200)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->date('establishment_date')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // ---- Assets -------------------------------------------------------------
            // Land and building are tracked independently: a church can own its
            // land but still be renting or constructing the building on it.
            $table->enum('land_status', ['rented', 'bought'])->nullable();
            $table->enum('building_status', ['rented', 'built', 'under_construction'])->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // pastor_name removed — leadership is now tracked in the
            // dedicated `leaderships` table (a church can have several
            // leaders: Pastor, Assistant Pastor, Elder, etc.)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('churches');
    }
};
