<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversation table for group chat support
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null'); //for group creator
            $table->timestamps();
        });

        // Pivot table for conversation participants
        Schema::create('conversation_user', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
             $table->enum('role', ['admin', 'member'])->default('member'); // for roles in group chat
            $table->primary(['conversation_id', 'user_id']);
        });

        // Message table with media support
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_type')->nullable(); // 'image', 'video', 'audio', 'file'
              // for threading support
            $table->foreignId('parent_id')->nullable()->constrained('messages')->onDelete('set null');
            $table->timestamp('edited_at')->nullable();
            // for soft deletion
            $table->softDeletes();
            $table->timestamps();
        });

        // Pivot table to track which users have read which messages
        Schema::create('message_read_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('read_at')->useCurrent();
            $table->unique(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_read_user');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('conversations');
    }
};
