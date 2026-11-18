<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eurocross_settings', function (Blueprint $table): void {
            $table->string('gumroad_sale_id', 100)->nullable()->after('subscription_id');
            $table->timestamp('next_billing_date')->nullable()->after('subscription_current_period_end');
            $table->timestamp('words_reset_at')->nullable()->after('next_billing_date');
        });
    }

    public function down(): void
    {
        Schema::table('eurocross_settings', function (Blueprint $table): void {
            $table->dropColumn(['gumroad_sale_id', 'next_billing_date', 'words_reset_at']);
        });
    }
};
