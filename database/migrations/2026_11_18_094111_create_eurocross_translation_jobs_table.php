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
        Schema::create('eurocross_translation_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('eurocross_settings')->cascadeOnDelete();
            $table->foreignId('translation_id')->constrained('eurocross_translations')->cascadeOnDelete();
            $table->string('batch_id', 36)->nullable();
            $table->string('status', 24)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status'], 'ec_translation_jobs_shop_status_index');
            $table->index(['batch_id', 'status'], 'ec_translation_jobs_batch_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eurocross_translation_jobs');
    }
};
