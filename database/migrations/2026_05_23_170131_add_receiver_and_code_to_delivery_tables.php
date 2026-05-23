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
        // Add receiver_phone to delivery_orders
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->string('receiver_phone')->nullable()->after('dropoff_lng');
        });

        // Add code columns to deliveries
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('delivery_code', 6)->nullable()->after('auto_release_at');
            $table->timestamp('delivery_code_expires_at')->nullable()->after('delivery_code');
            $table->timestamp('code_verified_at')->nullable()->after('delivery_code_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn('receiver_phone');
        });
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['delivery_code', 'delivery_code_expires_at', 'code_verified_at']);
        });
    }
};
