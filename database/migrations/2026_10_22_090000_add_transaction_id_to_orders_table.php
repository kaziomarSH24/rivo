<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'transaction_id')) {
                $table->string('transaction_id')->nullable()->after('payment_method')->index();
            }
            if (!Schema::hasColumn('orders', 'payment_metadata')) {
                $table->json('payment_metadata')->nullable()->after('payment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'transaction_id')) {
                $table->dropColumn('transaction_id');
            }
            if (Schema::hasColumn('orders', 'payment_metadata')) {
                $table->dropColumn('payment_metadata');
            }
        });
    }
};