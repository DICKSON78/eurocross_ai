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
        Schema::create('eurocross_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('eurocross_settings')->cascadeOnDelete();
            $table->string('source_type', 64);
            $table->string('source_id', 64);
            $table->string('field', 64);
            $table->string('source_locale', 12)->default('en');
            $table->string('target_locale', 12);
            $table->string('source_hash', 64);
            $table->longText('source_text');
            $table->longText('translated_text')->nullable();
            $table->string('status', 24)->default('pending');
            $table->boolean('manually_edited')->default(false);
            $table->string('provider', 32)->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->unsignedInteger('words')->default(0);
            $table->unsignedInteger('tokens')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->string('batch_id', 36)->nullable();
            $table->timestamps();

            $table->unique(
                ['shop_id', 'source_type', 'source_id', 'field', 'source_locale', 'target_locale'],
                'ec_translations_dedupe_unique'
            );
            $table->index(['shop_id', 'status'], 'ec_translations_shop_status_index');
            $table->index(['shop_id', 'target_locale'], 'ec_translations_shop_target_index');
            $table->index('batch_id', 'ec_translations_batch_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eurocross_translations');
    }
};
