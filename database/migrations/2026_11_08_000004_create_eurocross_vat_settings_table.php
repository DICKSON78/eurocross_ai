<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eurocross_vat_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('eurocross_settings')->onDelete('cascade');
            $table->string('default_country')->default('TZ'); // Customer default country
            $table->string('vat_number')->nullable(); // Shop's VAT number
            $table->boolean('use_oss')->default(false); // Use One Stop Shop
            $table->string('viies_status')->default('pending'); // VIES validation status
            $table->timestamp('viies_last_checked')->nullable();
            $table->decimal('foreign_vat_rate', 5, 2)->default(0); // Customer's country VAT rate
            $table->timestamps();

            $table->index(['shop_id', 'default_country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eurocross_vat_settings');
    }
};
