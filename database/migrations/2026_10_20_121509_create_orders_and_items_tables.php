<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('user_addresses')->nullOnDelete();
            $table->json('delivery_address_json')->nullable();
            $table->string('status')->default('new'); // new, preparing, ready_for_pickup, out_for_delivery, delivered, cancelled
            $table->string('delivery_type')->default('express'); // express, standard, pickup
            $table->string('estimated_delivery_time')->default('~15 min');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0.00);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2);
            $table->string('payment_method')->default('mastercard'); // apple_pay, visa, mastercard, cash
            $table->string('payment_status')->default('paid'); // pending, paid, refunded
            $table->text('notes')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('order_number');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_flavor')->nullable();
            $table->string('variant_size')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('total', 10, 2);
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};