<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('walk_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('walk_routes')->nullOnDelete();
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->integer('duration_seconds')->default(0);
            $table->integer('steps')->nullable();
            $table->integer('calories')->default(0);
            $table->decimal('avg_speed_kmh', 5, 2)->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->json('route_coordinates')->nullable(); // array of {lat, lng}
            $table->integer('xp_earned')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walk_sessions');
    }
};
