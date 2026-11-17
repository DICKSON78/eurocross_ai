<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eurocross_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50); // lemon_squeezy, paddle, stripe
            $table->string('event', 100); // invoice.paid, customer.subscription.created, etc.
            $table->text('payload'); // raw webhook payload
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->string('signature'); // HMAC signature for verification
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'event', 'processed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eurocross_webhooks');
    }
};
