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
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->default('android'); // android, ios
            $table->string('minimum_version'); // e.g. "1.0.5"
            $table->string('latest_version');  // e.g. "1.2.0"
            $table->string('update_url')->nullable();
            $table->boolean('force_update')->default(false);
            $table->text('update_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
