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
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->string('product_name');
            $table->text('product_description')->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->string('pickup_address');
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->string('dropoff_address');
            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();
            $table->date('preferred_date')->nullable();
            $table->enum('status', [
                'open',       // accepting bids
                'assigned',   // vendor picked a driver
                'active',     // driver accepted, in progress
                'delivered',  // driver marked delivered
                'completed',  // vendor confirmed
                'disputed',   // dispute raised
                'cancelled',
            ])->default('open');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};
