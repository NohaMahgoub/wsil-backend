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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('delivery_price', 10, 2);
            $table->decimal('service_fee', 10, 2)->default(0.00);
            $table->decimal('total_charged', 10, 2);
            $table->decimal('driver_lat', 10, 7)->nullable();
            $table->decimal('driver_lng', 10, 7)->nullable();
            $table->enum('status', [
                'in_progress',
                'delivered',   // driver marked
                'completed',   // vendor confirmed
                'disputed',
            ])->default('in_progress');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('auto_release_at')->nullable(); // 24hr timeout
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
