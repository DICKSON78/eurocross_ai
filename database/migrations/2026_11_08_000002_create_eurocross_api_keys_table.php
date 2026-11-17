<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eurocross_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('eurocross_settings')->onDelete('cascade');
            $table->string('provider'); // openai, deepl, google, weglot
            $table->string('key_name'); // e.g., 'primary', 'fallback'
            $table->text('encrypted_key'); // encrypted storage
            $table->boolean('active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->integer('daily_usage')->default(0);
            $table->integer('monthly_usage')->default(0);
            $table->timestamps();

            $table->index(['shop_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eurocross_api_keys');
    }
};
