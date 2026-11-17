<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eurocross_translation_usage', function (Blueprint $table): void {
            $table->decimal('ai_cost', 12, 6)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('eurocross_translation_usage', function (Blueprint $table): void {
            $table->decimal('ai_cost', 10, 2)->default(0)->change();
        });
    }
};
