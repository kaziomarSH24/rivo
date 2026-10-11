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
        Schema::table('post_media', function (Blueprint $table) {
            $table->foreignId('post_id')->after('id')->constrained('posts')->cascadeOnDelete();
            $table->string('media_path')->after('post_id');
            $table->string('media_type')->default('image')->after('media_path');
            $table->string('thumbnail_path')->nullable()->after('media_type');
            $table->unsignedInteger('order')->default(0)->after('thumbnail_path');
        });

        Schema::table('post_likes', function (Blueprint $table) {
            $table->foreignId('post_id')->after('id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->after('post_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['post_id', 'user_id']);
        });

        Schema::table('post_comments', function (Blueprint $table) {
            $table->foreignId('post_id')->after('id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->after('post_id')->constrained('users')->cascadeOnDelete();
            $table->text('content')->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_comments', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['post_id', 'user_id', 'content']);
        });

        Schema::table('post_likes', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropForeign(['user_id']);
            $table->dropUnique(['post_id', 'user_id']);
            $table->dropColumn(['post_id', 'user_id']);
        });

        Schema::table('post_media', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropColumn(['post_id', 'media_path', 'media_type', 'thumbnail_path', 'order']);
        });
    }
};
