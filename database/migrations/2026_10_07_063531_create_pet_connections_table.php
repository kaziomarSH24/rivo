<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->onDelete('cascade');
            $table->foreignId('connected_pet_id')->constrained('pets')->onDelete('cascade');
            $table->enum('status', ['pending', 'accepted', 'declined', 'blocked'])->default('pending');
            $table->enum('source', ['match', 'direct_request'])->default('direct_request');
            $table->timestamps();

            $table->unique(['pet_id', 'connected_pet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_connections');
    }
};
