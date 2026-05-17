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
        Schema::table('deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('deliveries', 'vendor_fee_percentage')) {
                $table->decimal('vendor_fee_percentage', 5, 2)->default(10.00)->after('total_charged');
            }
            if (!Schema::hasColumn('deliveries', 'driver_fee_percentage')) {
                $table->decimal('driver_fee_percentage', 5, 2)->default(5.00)->after('vendor_fee_percentage');
            }
            if (!Schema::hasColumn('deliveries', 'driver_service_fee')) {
                $table->decimal('driver_service_fee', 8, 2)->default(0)->after('driver_fee_percentage');
            }
            if (!Schema::hasColumn('deliveries', 'driver_earnings')) {
                $table->decimal('driver_earnings', 8, 2)->default(0)->after('driver_service_fee');
            }
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            table->dropColumn('vendor_fee_percentage','driver_fee_percentage','driver_service_fee','driver_earnings');
        });
    }
};
