<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eurocross_settings', function (Blueprint $table) {
            $table->id();
            $table->string('api_key')->nullable();
            $table->string('locale')->default('en');
            $table->string('plan')->default('starter');
            $table->boolean('trial')->default(true);
            $table->timestamp('trial_expires')->nullable();
            $table->integer('active_accounts')->default(0);
            $table->decimal('monthly_recurring_revenue', 15, 2)->default(0);
            $table->decimal('monthly_profit', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eurocross_settings');
    }
};
