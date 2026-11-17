<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eurocross_api_keys', function (Blueprint $table): void {
            $table->string('key_hash', 64)->nullable()->after('encrypted_key');
            $table->unique('key_hash', 'eurocross_api_keys_key_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('eurocross_api_keys', function (Blueprint $table): void {
            $table->dropUnique('eurocross_api_keys_key_hash_unique');
            $table->dropColumn('key_hash');
        });
    }
};
