<?php

namespace App\Jobs;

use App\Models\EuroCrossAuditLog;
use App\Models\EuroCrossManualPayment;
use App\Models\EuroCrossSettings;
use App\Models\EuroCrossTransaction;
use App\Models\EuroCrossWebhook;
use App\Models\User;
use App\Services\ConnectedSiteService;
use App\Services\Payment\GumroadProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessGumroadWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public string $eventId,
        public string $eventType = 'sale',
        public ?int $webhookRecordId = null,
    ) {}

    public function handle(GumroadProvider $gumroad, ?ConnectedSiteService $connectedSites = null): void
    {
        $payload = $this->payload;
        $record = $this->webhookRecordId !== null
            ? EuroCrossWebhook::findOrFail($this->webhookRecordId)
            : EuroCrossWebhook::firstOrCreate(
                ['provider' => 'gumroad', 'event_id' => $this->eventId],
                ['event' => $this->eventType, 'payload' => $payload, 'signature' => '', 'processed' => false],
            );

        if ($record->processed) {
            return;
        }

        $productId = (string) ($payload['product_id'] ?? '');
        $permalink = (string) ($payload['product_permalink'] ?? '');
        $plan = $gumroad->planFromProductId($productId) ?? $gumroad->planFromPermalink($permalink);

        Log::channel('api')->info('Gumroad webhook processing', [
            'product_id' => $productId,
            'product_permalink' => $permalink,
            'mapped_plan' => $plan,
            'event' => $this->eventType,
        ]);

        if ($plan === null) {
            $record->forceFill(['processed' => true, 'processed_at' => now(), 'error' => 'unknown_product'])->save();

            return;
        }

        $subscriptionId = (string) ($payload['subscription_id'] ?? '');
        $verifiedStatus = $this->verifySubscription($subscriptionId);

        if ($verifiedStatus === 'cancelled' || $verifiedStatus === 'ended') {
            // Gumroad says the subscription is no longer active; never activate.
            if (in_array($this->eventType, ['sale', 'recurring_charge', 'subscription_updated'], true)) {
                $record->forceFill(['processed' => true, 'processed_at' => now(), 'error' => 'subscription_not_active'])->save();

                return;
            }
        }

        $email = strtolower(trim((string) ($payload['email'] ?? $payload['buyer_email'] ?? '')));
        $user = $email !== '' ? User::whereRaw('lower(email) = ?', [$email])->first() : null;
        $settings = $user?->euroCrossSettings()->first();

        if ($settings === null) {
            $this->storeManualPayment($plan, $productId, $permalink, $email);
            $record->forceFill(['processed' => true, 'processed_at' => now(), 'error' => 'pending_review'])->save();

            return;
        }

        $nextBilling = $this->parseDate($payload['next_charge_date'] ?? $payload['ends_at'] ?? null);
        $saleId = (string) ($payload['sale_id'] ?? $payload['id'] ?? '');

        $conflictingActivation = false;

        DB::transaction(function () use ($settings, $plan, $saleId, $subscriptionId, $nextBilling, $record, $user, &$conflictingActivation): void {
            $locked = EuroCrossSettings::where('id', $settings->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->only(['plan', 'subscription_status', 'subscription_id']);

            if (in_array($this->eventType, ['sale', 'recurring_charge', 'subscription_updated', 'subscription_restarted'], true)) {
                $incomingSubscriptionId = $subscriptionId !== '' ? $subscriptionId : null;
                $hasDifferentActiveSubscription = $locked->hasActiveSubscription()
                    && filled($locked->subscription_id)
                    && $incomingSubscriptionId !== null
                    && ! hash_equals((string) $locked->subscription_id, $incomingSubscriptionId);

                if ($hasDifferentActiveSubscription) {
                    $conflictingActivation = true;
                    Log::channel('api')->warning('Gumroad webhook conflicts with active subscription', [
                        'shop_id' => $locked->id,
                        'user_id' => $user->id,
                        'existing_subscription_id' => $locked->subscription_id,
                        'incoming_subscription_id' => $incomingSubscriptionId,
                        'event' => $this->eventType,
                    ]);
                    EuroCrossAuditLog::create([
                        'shop_id' => $locked->id,
                        'action' => 'gumroad_subscription_conflict',
                        'entity_type' => 'eurocross_settings',
                        'entity_id' => $locked->id,
                        'old_value' => json_encode($previous),
                        'new_value' => json_encode(['subscription_id' => $incomingSubscriptionId, 'plan' => $plan, 'event' => $this->eventType]),
                    ]);
                    $record->forceFill([
                        'shop_id' => $locked->id,
                        'processed' => true,
                        'processed_at' => now(),
                        'error' => 'conflicting_active_subscription',
                    ])->save();

                    return;
                }

                $isNewActivation = ! hash_equals((string) $locked->subscription_id, (string) $incomingSubscriptionId)
                    || $locked->plan !== $plan
                    || ! $locked->hasActiveSubscription();

                $locked->forceFill([
                    'plan' => $plan,
                    'subscription_provider' => 'gumroad',
                    'subscription_id' => $subscriptionId !== '' ? $subscriptionId : $locked->subscription_id,
                    'gumroad_sale_id' => $saleId !== '' ? $saleId : $locked->gumroad_sale_id,
                    'subscription_status' => 'active',
                    'subscription_current_period_end' => $nextBilling ?? $locked->subscription_current_period_end,
                    'next_billing_date' => $nextBilling ?? $locked->next_billing_date,
                    'words_reset_at' => $isNewActivation ? now() : ($locked->words_reset_at ?? now()),
                    'trial' => false,
                    'trial_expires' => null,
                    'grace_expires' => null,
                ])->save();
            } elseif (in_array($this->eventType, ['refund', 'dispute', 'dispute_won'], true)) {
                $locked->forceFill(['subscription_status' => 'suspended'])->save();
            } elseif ($this->eventType === 'cancellation') {
                $locked->forceFill([
                    'subscription_status' => 'canceled',
                    'subscription_current_period_end' => $nextBilling ?? $locked->subscription_current_period_end,
                    'next_billing_date' => $nextBilling ?? $locked->next_billing_date,
                ])->save();
            } elseif ($this->eventType === 'subscription_ended') {
                $locked->forceFill(['subscription_status' => 'ended'])->save();
            } elseif (str_contains($this->eventType, 'failed')) {
                $locked->forceFill([
                    'subscription_status' => 'past_due',
                    'grace_expires' => now()->addDays((int) config('payment.trial.grace_period_days', 3)),
                ])->save();
            }

            EuroCrossTransaction::updateOrCreate(
                ['provider' => 'gumroad', 'event_id' => $this->eventId],
                [
                    'shop_id' => $locked->id,
                    'transaction_id' => $saleId ?: null,
                    'type' => $this->eventType,
                    'status' => $this->transactionStatus(),
                    'plan' => $plan,
                    'amount' => 0,
                    'vat_amount' => 0,
                    'currency' => 'EUR',
                    'payload' => $this->payload,
                    'occurred_at' => now(),
                ],
            );

            EuroCrossAuditLog::create([
                'shop_id' => $locked->id,
                'action' => 'gumroad_'.$this->eventType,
                'entity_type' => 'eurocross_settings',
                'entity_id' => $locked->id,
                'old_value' => json_encode($previous),
                'new_value' => json_encode($locked->only(['plan', 'subscription_status', 'subscription_id', 'next_billing_date'])),
            ]);

            $record->forceFill([
                'shop_id' => $locked->id,
                'processed' => true,
                'processed_at' => now(),
                'error' => null,
            ])->save();
        });

        if ($conflictingActivation) {
            $this->storeManualPayment($plan, $productId, $permalink, $email);
            $record->refresh();
            if ($record->shop_id === null) {
                $record->forceFill(['shop_id' => $settings->id])->save();
            }

            return;
        }

        $connectedSites?->sync($settings->fresh());
    }

    private function verifySubscription(string $subscriptionId): ?string
    {
        if ($subscriptionId === '' || ! filled(config('payment.gumroad.access_token'))) {
            return null;
        }

        try {
            $response = Http::connectTimeout(3)->timeout(15)->get(
                'https://api.gumroad.com/v2/subscribers/'.rawurlencode($subscriptionId),
                ['access_token' => config('payment.gumroad.access_token')],
            );

            if (! $response->successful()) {
                return null;
            }

            return (string) $response->json('subscriber.subscription_status', '') ?: null;
        } catch (Throwable $exception) {
            Log::channel('api')->warning('Gumroad subscriber verification failed');

            return null;
        }
    }

    private function storeManualPayment(string $plan, string $productId, string $permalink, string $email): void
    {
        EuroCrossManualPayment::firstOrCreate(
            ['event_id' => $this->eventId],
            [
                'sale_id' => (string) ($this->payload['sale_id'] ?? $this->payload['id'] ?? '') ?: null,
                'subscription_id' => (string) ($this->payload['subscription_id'] ?? '') ?: null,
                'email' => $email !== '' ? $email : 'unknown@example.invalid',
                'plan' => $plan,
                'product_id' => $productId ?: null,
                'permalink' => $permalink ?: null,
                'payload' => $this->payload,
                'status' => 'pending',
            ],
        );
    }

    private function transactionStatus(): string
    {
        return match ($this->eventType) {
            'refund' => 'refunded',
            'dispute', 'dispute_won' => 'disputed',
            default => 'paid',
        };
    }

    private function parseDate(mixed $value): ?\DateTimeInterface
    {
        if (empty($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (Throwable $exception) {
            return null;
        }
    }
}
