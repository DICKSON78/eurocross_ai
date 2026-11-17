<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eurocross_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('eurocross_settings')->onDelete('cascade');
            $table->string('action'); // e.g., 'plan_changed', 'api_key_updated', 'vat_config_updated', 'refund_processed'
            $table->string('entity_type'); // e.g., 'account', 'subscription', 'vat_setting'
            $table->unsignedInteger('entity_id');
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eurocross_audit_logs');
    }
};
