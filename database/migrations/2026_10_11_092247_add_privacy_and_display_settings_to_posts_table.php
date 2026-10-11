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
            $table->boolean('hide_like_count')->default(false)->after('visibility');
            $table->boolean('hide_share_count')->default(false)->after('hide_like_count');
            $table->boolean('turn_off_commenting')->default(false)->after('hide_share_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['hide_like_count', 'hide_share_count', 'turn_off_commenting']);
        });
    }
};
