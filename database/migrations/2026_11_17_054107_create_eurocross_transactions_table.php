<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eurocross_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('eurocross_settings')->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('event_id', 100)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->string('type', 50)->default('subscription');
            $table->string('status', 50)->default('pending');
            $table->string('plan', 50)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->char('country', 2)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id'], 'eurocross_transactions_provider_event_unique');
            $table->index(['shop_id', 'occurred_at'], 'eurocross_transactions_shop_occurred_index');
            $table->index(['status', 'plan'], 'eurocross_transactions_status_plan_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eurocross_transactions');
    }
};
