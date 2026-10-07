<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_swipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->onDelete('cascade');
            $table->foreignId('target_pet_id')->constrained('pets')->onDelete('cascade');
            $table->enum('action', ['liked', 'passed']);
            $table->timestamps();
            
            $table->unique(['pet_id', 'target_pet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_swipes');
    }
};
