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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('payable');
            $table->string('gateway'); // like: 'stripe', 'paypal'
            $table->string('gateway_transaction_id')->unique()->nullable();
            $table->string('payment_intent_id')->nullable(); // For payment intents
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->enum('status', ['pending', 'success', 'failed', 'refunded', 'partially_refunded'])->default('pending');
            $table->json('metadata')->nullable(); // for storing additional info
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
