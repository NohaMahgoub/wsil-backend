<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE delivery_orders MODIFY COLUMN status ENUM(
            'open',
            'assigned',
            'picking_up',
            'in_transit',
            'active',
            'delivered',
            'completed',
            'disputed',
            'cancelled'
        ) NOT NULL DEFAULT 'open'");

        DB::statement("ALTER TABLE deliveries MODIFY COLUMN status ENUM(
            'in_progress',
            'picking_up',
            'in_transit',
            'delivered',
            'completed',
            'cancelled'
        ) NOT NULL DEFAULT 'in_progress'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE delivery_orders MODIFY COLUMN status ENUM(
            'open','assigned','active','delivered','completed','disputed','cancelled'
        ) NOT NULL DEFAULT 'open'");

        DB::statement("ALTER TABLE deliveries MODIFY COLUMN status ENUM(
            'in_progress','delivered','completed','cancelled'
        ) NOT NULL DEFAULT 'in_progress'");
    }
};