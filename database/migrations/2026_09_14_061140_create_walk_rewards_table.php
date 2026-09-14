<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('walk_rewards', function (Blueprint $table) {
            $table->id();
            $table->integer('level'); // 1, 2, 3...
            $table->integer('required_streak_days'); // 3, 7, 14, 30, 100
            $table->integer('bonus_xp'); // 20, 50, 100...
            $table->string('reward_title')->nullable(); // Level 1, Level 2...
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walk_rewards');
    }
};
