<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eurocross_webhooks', function (Blueprint $table): void {
            $table->foreignId('shop_id')->nullable()->after('provider')
                ->constrained('eurocross_settings')->nullOnDelete();
            $table->string('event_id')->nullable()->after('event');
            $table->text('error')->nullable()->after('payload');

            $table->unique(['provider', 'event_id'], 'eurocross_webhooks_provider_event_id_unique');
            $table->index(['shop_id', 'created_at'], 'eurocross_webhooks_shop_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('eurocross_webhooks', function (Blueprint $table): void {
            $table->dropIndex('eurocross_webhooks_shop_created_index');
            $table->dropUnique('eurocross_webhooks_provider_event_id_unique');
            $table->dropColumn(['shop_id', 'event_id', 'error']);
        });
    }
};
