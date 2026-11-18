<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eurocross_manual_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id', 191)->unique();
            $table->string('sale_id', 100)->nullable();
            $table->string('subscription_id', 100)->nullable();
            $table->string('email', 191);
            $table->string('plan', 50)->nullable();
            $table->string('product_id', 100)->nullable();
            $table->string('permalink', 100)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('shop_id')->nullable()->constrained('eurocross_settings')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('linked_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['status', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eurocross_manual_payments');
    }
};
