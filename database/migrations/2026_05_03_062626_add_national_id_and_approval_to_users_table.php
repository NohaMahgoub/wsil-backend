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
        Schema::table('users', function (Blueprint $table) {
            $table->string('national_id')->nullable()->after('phone');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])
                ->default('pending')->after('national_id');
            $table->timestamp('approved_at')->nullable()->after('approval_status');
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'national_id',
                'approval_status',
                'approved_at',
                'approved_by',
            ]);
        });
    }
};
