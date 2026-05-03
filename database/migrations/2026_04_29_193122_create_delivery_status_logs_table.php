<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->string('status');
            $table->foreignId('changed_by')->constrained('users')->onDelete('cascade');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // Add new status values to delivery_orders
        Schema::table('deliveries', function (Blueprint $table) {
            // status will now be: assigned, picking_up, in_transit, delivered, completed
            $table->timestamp('picking_up_at')->nullable()->after('confirmed_at');
            $table->timestamp('in_transit_at')->nullable()->after('picking_up_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_status_logs');
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['picking_up_at', 'in_transit_at']);
        });
    }
};