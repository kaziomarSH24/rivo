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
        Schema::table('posts', function (Blueprint $table) {
            if (!Schema::hasColumn('posts', 'title')) {
                $table->string('title')->nullable()->after('pet_id');
            }
            if (!Schema::hasColumn('posts', 'metadata')) {
                $table->json('metadata')->nullable()->after('content');
            }
            if (!Schema::hasColumn('posts', 'reaction_counts')) {
                $table->json('reaction_counts')->nullable()->after('likes_count');
            }
        });

        Schema::dropIfExists('post_likes');

        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reactable');
            $table->string('reaction_type');
            $table->timestamps();

            $table->unique(['user_id', 'reactable_type', 'reactable_id', 'reaction_type'], 'user_reactable_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reactions');

        Schema::create('post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['title', 'metadata', 'reaction_counts']);
        });
    }
};
