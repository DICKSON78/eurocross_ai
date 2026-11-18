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
        Schema::create('eurocross_connected_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('eurocross_settings')->cascadeOnDelete();
            $table->string('site_url', 500);
            $table->text('site_secret');
            $table->timestamps();

            $table->index('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eurocross_connected_sites');
    }
};
