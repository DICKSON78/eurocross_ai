<?php

namespace App\Jobs;

use App\Models\EuroCrossSettings;
use App\Models\EuroCrossWebhook;
use App\Services\Payment\GumroadProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessGumroadWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public array $payload) {}

    public function handle(GumroadProvider $gumroad): void
    {
        $payload = $this->payload;
        $saleId = (string) ($payload['sale_id'] ?? $payload['id'] ?? '');
        $eventId = $saleId !== '' ? $saleId : hash('sha256', json_encode($payload));

        if (EuroCrossWebhook::where('provider', 'gumroad')->where('event_id', $eventId)->where('status', 'processed')->exists()) {
            return;
        }

        $record = EuroCrossWebhook::firstOrCreate(
            ['provider' => 'gumroad', 'event_id' => $eventId],
            ['event_type' => (string) ($payload['event'] ?? 'sale'), 'payload' => $payload, 'status' => 'received'],
        );

        $productId = (string) ($payload['product_id'] ?? '');
        $permalink = (string) ($payload['product_permalink'] ?? '');
        $plan = $gumroad->planFromProductId($productId) ?? $gumroad->planFromPermalink($permalink);

        Log::info('Gumroad webhook received', [
            'product_id' => $productId,
            'product_permalink' => $permalink,
            'mapped_plan' => $plan,
        ]);

        if ($plan === null) {
            Log::warning('Gumroad unknown product', ['product_id' => $productId, 'permalink' => $permalink]);
            $record->forceFill(['status' => 'ignored', 'error' => 'Unknown product'])->save();

            return;
        }

        // Verify subscription status with Gumroad API when subscription_id present
        $subscriptionId = (string) ($payload['subscription_id'] ?? '');
        if ($subscriptionId !== '' && filled(config('payment.gumroad.access_token'))) {
            try {
                $resp = Http::connectTimeout(3)->timeout(15)->get(
                    'https://api.gumroad.com/v2/subscribers/'.rawurlencode($subscriptionId),
                    ['access_token' => config('payment.gumroad.access_token')],
                );
                if ($resp->successful()) {
                    $status = (string) $resp->json('subscriber.subscription_status', '');
                    if (in_array($status, ['cancelled', 'canceled', 'failed', 'ended'], true)) {
                        // still record but may deactivate below based on event
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Gumroad subscriber verification failed', ['error' => $e->getMessage()]);
            }
        }

        $email = (string) ($payload['email'] ?? '');
        $settings = $email !== '' ? EuroCrossSettings::where('billing_email', $email)->first() : null;

        if ($settings === null && $email !== '') {
            // manual review queue: store webhook as pending review
            $record->forceFill(['status' => 'pending_review', 'shop_id' => null])->save();
            Log::warning('Gumroad payment unmatched email', ['email' => $email, 'sale_id' => $saleId]);

            return;
        }

        $eventType = (string) ($payload['event'] ?? 'sale');
        $isRecurring = filter_var($payload['is_recurring_charge'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $nextBilling = $payload['next_charge_date'] ?? $payload['subscription_end_date'] ?? null;

        DB::transaction(function () use ($settings, $plan, $payload, $subscriptionId, $eventType, $nextBilling, $record) {
            $old = $settings->only(['plan', 'subscription_status', 'subscription_id']);

            if (in_array($eventType, ['refund', 'dispute'], true)) {
                $settings->forceFill([
                    'subscription_status' => 'suspended',
                ])->save();
            } elseif ($eventType === 'subscription_ended') {
                $settings->forceFill([
                    'subscription_status' => 'ended',
                    'trial' => false,
                ])->save();
            } elseif ($eventType === 'cancellation') {
                // keep access until period end
                $settings->forceFill([
                    'subscription_status' => 'canceled',
                    'subscription_current_period_end' => $nextBilling ? new \DateTimeImmutable((string) $nextBilling) : $settings->subscription_current_period_end,
                ])->save();
            } elseif ($eventType === 'subscription_restarted') {
                $settings->forceFill([
                    'plan' => $plan,
                    'subscription_provider' => 'gumroad',
                    'subscription_id' => $subscriptionId !== '' ? $subscriptionId : $settings->subscription_id,
                    'subscription_status' => 'active',
                    'trial' => false,
                    'trial_expires' => null,
                    'grace_expires' => null,
                ])->save();
            } elseif ($eventType === 'failed_payment' || str_contains($eventType, 'failed')) {
                $settings->forceFill([
                    'subscription_status' => 'past_due',
                    'grace_expires' => now()->addDays((int) config('payment.trial.grace_period_days', 3)),
                ])->save();
            } else {
                // sale / recurring charge
                $settings->forceFill([
                    'plan' => $plan,
                    'subscription_provider' => 'gumroad',
                    'subscription_id' => $subscriptionId !== '' ? $subscriptionId : $settings->subscription_id,
                    'subscription_status' => 'active',
                    'billing_email' => (string) ($payload['email'] ?? $settings->billing_email),
                    'trial' => false,
                    'trial_expires' => null,
                    'grace_expires' => null,
                    'subscription_current_period_end' => $nextBilling ? new \DateTimeImmutable((string) $nextBilling) : $settings->subscription_current_period_end,
                ])->save();

                // reset monthly word counter: trackUsage creates rows; we don't delete but new month naturally.
            }

            $record->forceFill(['status' => 'processed', 'shop_id' => $settings->id])->save();
        });
    }
}
