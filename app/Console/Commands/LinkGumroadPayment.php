<?php

namespace App\Console\Commands;

use App\Models\EuroCrossManualPayment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LinkGumroadPayment extends Command
{
    protected $signature = 'gumroad:link-payment {event_id} {email} {--force}';

    protected $description = 'Link an unmatched Gumroad payment to a EuroCross user';

    public function handle(): int
    {
        $payment = EuroCrossManualPayment::where('event_id', $this->argument('event_id'))->first();

        if (! $payment instanceof EuroCrossManualPayment) {
            $this->error('Manual payment not found.');

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();

        if (! $user instanceof User) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        if ($payment->status === 'linked' && ! $this->option('force')) {
            $this->error('Payment is already linked. Use --force to relink.');

            return self::FAILURE;
        }

        $settings = $user->euroCrossSettings()->firstOrCreate([
            'billing_email' => $user->email,
        ], [
            'locale' => 'en',
            'plan' => 'starter',
            'trial' => true,
            'trial_expires' => now()->addDays((int) config('payment.trial.days', 7)),
        ]);

        DB::transaction(function () use ($payment, $user, $settings): void {
            $settings->forceFill([
                'plan' => $payment->plan ?? $settings->plan,
                'subscription_provider' => 'gumroad',
                'subscription_id' => $payment->subscription_id ?? $settings->subscription_id,
                'gumroad_sale_id' => $payment->sale_id ?? $settings->gumroad_sale_id,
                'subscription_status' => 'active',
                'trial' => false,
                'trial_expires' => null,
                'grace_expires' => null,
                'words_reset_at' => now(),
            ])->save();

            $payment->forceFill([
                'shop_id' => $settings->id,
                'status' => 'linked',
                'linked_by' => $user->id,
                'linked_at' => now(),
            ])->save();
        });

        $this->info("Linked payment {$payment->event_id} to {$user->email}.");

        return self::SUCCESS;
    }
}
