<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // Dog, Cat, etc.
            $table->string('name');
            $table->string('breed')->nullable();
            $table->integer('age_years')->nullable();
            $table->integer('age_months')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->enum('gender', ['Male', 'Female', 'Unknown'])->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
