<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->text('bio')->nullable();
            $table->json('traits')->nullable(); // e.g. ["Friendly", "High Energy"]
            $table->json('personality')->nullable(); // e.g. ["Playful", "Loyal"]
            $table->string('energy_level')->nullable(); // e.g. "High"
            $table->string('training_level')->nullable(); // e.g. "Advanced"
            $table->boolean('is_vaccinated')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn([
                'bio',
                'traits',
                'personality',
                'energy_level',
                'training_level',
                'is_vaccinated'
            ]);
        });
    }
};
