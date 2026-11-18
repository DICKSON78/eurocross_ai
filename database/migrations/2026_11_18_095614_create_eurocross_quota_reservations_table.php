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
        Schema::create('eurocross_quota_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('eurocross_settings')->cascadeOnDelete();
            $table->unsignedInteger('words');
            $table->string('token', 36)->unique();
            $table->string('status', 16)->default('reserved');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['shop_id', 'status', 'expires_at'], 'ec_quota_reservations_shop_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eurocross_quota_reservations');
    }
};
