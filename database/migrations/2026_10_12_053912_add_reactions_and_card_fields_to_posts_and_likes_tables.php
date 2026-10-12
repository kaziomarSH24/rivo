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
        if (!Schema::hasColumn('posts', 'title')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->string('title')->nullable()->after('pet_id');
                $table->json('metadata')->nullable()->after('content');
                $table->unsignedInteger('bones_count')->default(0)->after('likes_count');
                $table->unsignedInteger('fires_count')->default(0)->after('bones_count');
            });
        }

        Schema::table('post_likes', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropUnique('post_likes_post_id_user_id_unique');
            $table->string('reaction_type')->default('bone')->after('user_id');
            $table->unique(['post_id', 'user_id', 'reaction_type']);
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_likes', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropUnique(['post_id', 'user_id', 'reaction_type']);
            $table->dropColumn('reaction_type');
            $table->unique(['post_id', 'user_id']);
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['title', 'metadata', 'bones_count', 'fires_count']);
        });
    }
};
