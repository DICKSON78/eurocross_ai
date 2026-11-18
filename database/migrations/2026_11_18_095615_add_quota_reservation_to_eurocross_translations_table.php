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
        Schema::table('eurocross_translations', function (Blueprint $table): void {
            $table->foreignId('quota_reservation_id')
                ->nullable()
                ->after('batch_id')
                ->constrained('eurocross_quota_reservations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eurocross_translations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quota_reservation_id');
        });
    }
};
