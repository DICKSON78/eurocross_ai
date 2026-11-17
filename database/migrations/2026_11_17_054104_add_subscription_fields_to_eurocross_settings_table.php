<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eurocross_settings', function (Blueprint $table): void {
            $table->timestamp('trial_started_at')->nullable()->after('trial_expires');
            $table->timestamp('grace_expires')->nullable()->after('trial_started_at');
            $table->string('subscription_provider')->nullable()->after('grace_expires');
            $table->string('subscription_id')->nullable()->after('subscription_provider');
            $table->string('subscription_status')->default('none')->after('subscription_id');
            $table->timestamp('subscription_current_period_end')->nullable()->after('subscription_status');
            $table->string('customer_id')->nullable()->after('subscription_current_period_end');
            $table->string('billing_email')->nullable()->after('customer_id');

            $table->index(['subscription_status', 'plan'], 'eurocross_settings_subscription_plan_index');
        });
    }

    public function down(): void
    {
        Schema::table('eurocross_settings', function (Blueprint $table): void {
            $table->dropIndex('eurocross_settings_subscription_plan_index');
            $table->dropColumn([
                'trial_started_at',
                'grace_expires',
                'subscription_provider',
                'subscription_id',
                'subscription_status',
                'subscription_current_period_end',
                'customer_id',
                'billing_email',
            ]);
        });
    }
};
