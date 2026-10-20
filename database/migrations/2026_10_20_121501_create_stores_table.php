<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('pet_shop'); // pet_shop, veterinary, grooming, pharmacy
            $table->string('owner_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('street_address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->decimal('fulfillment_rate', 5, 2)->default(100.00);
            $table->unsignedInteger('avg_prep_time_minutes')->default(15);
            $table->decimal('delivery_fee', 10, 2)->default(0.00);
            $table->string('delivery_time_text')->default('15-20 min');
            $table->boolean('is_verified')->default(false);
            $table->string('verification_status')->default('pending'); // pending, verified, rejected
            $table->boolean('is_live')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['latitude', 'longitude']);
            $table->index('is_live');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};