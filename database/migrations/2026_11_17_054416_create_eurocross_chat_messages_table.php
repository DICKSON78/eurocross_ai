<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eurocross_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('eurocross_settings')->cascadeOnDelete();
            $table->string('visitor_id', 191);
            $table->string('language', 8)->default('en');
            $table->string('role');
            $table->text('message');
            $table->unsignedInteger('tokens')->default(0);
            $table->timestamps();

            $table->index(['shop_id', 'visitor_id', 'created_at'], 'eurocross_chat_messages_visitor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eurocross_chat_messages');
    }
};
