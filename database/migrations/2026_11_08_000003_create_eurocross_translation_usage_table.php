<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eurocross_translation_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('eurocross_settings')->onDelete('cascade');
            $table->integer('tokens_used')->default(0);
            $table->integer('strings_translated')->default(0);
            $table->integer('words_translated')->default(0);
            $table->decimal('ai_cost', 10, 2)->default(0);
            $table->date('usage_date');
            $table->timestamps();

            $table->index(['shop_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eurocross_translation_usage');
    }
};
