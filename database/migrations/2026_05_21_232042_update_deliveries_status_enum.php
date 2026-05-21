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
        DB::statement("ALTER TABLE deliveries MODIFY COLUMN status 
            ENUM(
                'in_progress',
                'active',
                'picking_up',
                'in_transit',
                'delivered',
                'completed',
                'disputed',
                'cancelled'
            ) NOT NULL DEFAULT 'in_progress'");
    }

    public function down(): void
    {
        
    }
};
