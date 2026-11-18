<?php

namespace App\Http\Controllers\EuroCross;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessGumroadWebhook;
use App\Models\EuroCrossAuditLog;
use App\Models\EuroCrossSettings;
use App\Models\EuroCrossTransaction;
use App\Models\EuroCrossWebhook;
use App\Services\Payment\GumroadWebhookVerifier;
use App\Services\Payment\LemonSqueezyProvider;
use App\Services\Payment\StripeProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(
        private LemonSqueezyProvider $lemonSqueezy,
        private StripeProvider $stripe,
        private GumroadWebhookVerifier $gumroadVerifier,
    ) {}

    public function gumroad(Request $request): JsonResponse
    {
        if (! $this->gumroadVerifier->verify($request)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $payload = $request->all();

        $sellerId = (string) ($payload['seller_id'] ?? '');
        $expectedSeller = (string) config('payment.gumroad.seller_id');

        if ($expectedSeller !== '' && $sellerId !== $expectedSeller) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $isTest = filter_var($payload['test'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($isTest && app()->environment('production')) {
            Log::channel('api')->info('Gumroad test ping ignored in production');

            return response()->json(['success' => true, 'status' => 'ignored_test']);
        }

        $eventType = (string) ($payload['event'] ?? $payload['event_type'] ?? 'sale');
        $saleId = (string) ($payload['sale_id'] ?? $payload['id'] ?? '');
        $subscriptionId = (string) ($payload['subscription_id'] ?? '');
        $eventId = md5(implode('|', ['gumroad', $eventType, $saleId, $subscriptionId, (string) ($payload['charge_id'] ?? '')]));

        Log::channel('api')->info('Gumroad ping received', [
            'event' => $eventType,
            'product_id' => (string) ($payload['product_id'] ?? ''),
            'product_permalink' => (string) ($payload['product_permalink'] ?? ''),
        ]);

        $record = EuroCrossWebhook::firstOrCreate(
            ['provider' => 'gumroad', 'event_id' => $eventId],
            [
                'event' => $eventType,
                'payload' => $payload,
                'signature' => (string) $request->header('X-Gumroad-Signature', ''),
                'processed' => false,
            ],
        );

        if (! $record->wasRecentlyCreated) {
            return response()->json(['success' => true, 'status' => 'duplicate']);
        }

        ProcessGumroadWebhook::dispatch($payload, $eventId, $eventType, $record->id);

        return response()->json(['success' => true]);
    }

    public function lemonSqueezy(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('X-Signature', $request->header('Signature', ''));

        if (! $this->lemonSqueezy->verifyWebhook($payload, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $eventId = (string) (data_get($decoded, 'meta.event_id')
            ?? data_get($decoded, 'id')
            ?? data_get($decoded, 'data.id')
            ?? hash('sha256', $payload));

        $eventType = (string) (data_get($decoded, 'data.type')
            ?? data_get($decoded, 'meta.event_name')
            ?? data_get($decoded, 'event_name')
            ?? 'unknown');

        $attributes = data_get($decoded, 'data.attributes', data_get($decoded, 'attributes', []));
        $metadata = data_get($attributes, 'meta.custom_data', data_get($attributes, 'custom_data', data_get($decoded, 'meta.custom_data', [])));
        $shopId = $this->resolveShopId($metadata, data_get($decoded, 'meta.custom_data', []));

        $record = $this->recordWebhook('lemon_squeezy', $eventId, $eventType, $decoded, $shopId, $signature);

        if ($record === null) {
            return response()->json(['success' => true, 'status' => 'duplicate'], 200);
        }

        try {
            $this->handleLemonSqueezy($eventType, $attributes, $metadata, $shopId, $record, $eventId, $decoded);
            $record->markProcessed();
        } catch (Throwable $exception) {
            $record->markFailed($exception->getMessage());

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }

        return response()->json(['success' => true]);
    }

    public function stripe(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');

        if (! $this->stripe->verifyWebhook($payload, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $eventId = (string) data_get($decoded, 'id', hash('sha256', $payload));
        $eventType = (string) data_get($decoded, 'type', 'unknown');
        $object = data_get($decoded, 'data.object', []);
        $metadata = is_array($object) ? data_get($object, 'metadata', []) : [];
        $shopId = $this->resolveShopId($metadata, []);

        $record = $this->recordWebhook('stripe', $eventId, $eventType, $decoded, $shopId, $signature);

        if ($record === null) {
            return response()->json(['success' => true, 'status' => 'duplicate'], 200);
        }

        try {
            $this->handleStripe($eventType, $object, $metadata, $shopId, $record, $eventId, $decoded);
            $record->markProcessed();
        } catch (Throwable $exception) {
            $record->markFailed($exception->getMessage());

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }

        return response()->json(['success' => true]);
    }

    /** @param array<string, mixed> $decoded */
    private function recordWebhook(
        string $provider,
        string $eventId,
        string $eventType,
        array $decoded,
        ?int $shopId,
        string $signature,
    ): ?EuroCrossWebhook {
        $record = EuroCrossWebhook::firstOrCreate(
            ['provider' => $provider, 'event_id' => $eventId],
            [
                'event' => $eventType,
                'shop_id' => $shopId,
                'payload' => $decoded,
                'signature' => $signature,
                'processed' => false,
            ],
        );

        return $record->wasRecentlyCreated ? $record : null;
    }

    /** @param array<string, mixed> $metadata */
    private function resolveShopId(array $metadata, array $fallbackMetadata): ?int
    {
        $value = data_get($metadata, 'shop_id', data_get($fallbackMetadata, 'shop_id'));

        if (! is_scalar($value)) {
            return null;
        }

        $shopId = (int) $value;

        return $shopId > 0 ? $shopId : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $decoded
     */
    private function handleLemonSqueezy(
        string $eventType,
        array $attributes,
        array $metadata,
        ?int $shopId,
        EuroCrossWebhook $record,
        string $eventId,
        array $decoded,
    ): void {
        $settings = $shopId !== null ? EuroCrossSettings::find($shopId) : null;

        if (str_contains($eventType, 'subscription')) {
            $status = (string) data_get($attributes, 'status', $eventType);
            $subscriptionId = (string) (data_get($attributes, 'subscription_id')
                ?? data_get($attributes, 'id')
                ?? data_get($attributes, 'meta.subscription_id')
                ?? '');
            $plan = $this->planFromMetadataOrPrice($metadata, data_get($attributes, 'variant_name'), data_get($attributes, 'product_name'));
            $customerId = (string) (data_get($attributes, 'customer_id') ?? data_get($attributes, 'customer.id') ?? '');
            $email = (string) (data_get($attributes, 'user_email') ?? data_get($attributes, 'customer_email') ?? data_get($metadata, 'email', ''));
            $periodEnd = $this->parseDate(data_get($attributes, 'renews_at', data_get($attributes, 'ends_at')));

            if ($settings !== null) {
                $oldValues = $settings->only(['plan', 'subscription_status', 'subscription_id', 'subscription_provider']);

                $settings->forceFill([
                    'plan' => $plan,
                    'subscription_provider' => 'lemon_squeezy',
                    'subscription_id' => $subscriptionId !== '' ? $subscriptionId : $settings->subscription_id,
                    'subscription_status' => $this->normalizeLemonSqueezyStatus($status),
                    'subscription_current_period_end' => $periodEnd,
                    'customer_id' => $customerId !== '' ? $customerId : $settings->customer_id,
                    'billing_email' => $email !== '' ? $email : $settings->billing_email,
                    'trial' => false,
                    'trial_expires' => null,
                    'grace_expires' => null,
                ])->save();

                $this->audit($settings, 'webhook_subscription_updated', $oldValues, [
                    'provider' => 'lemon_squeezy',
                    'event' => $eventType,
                    'status' => $settings->subscription_status,
                    'plan' => $settings->plan,
                ], $record);
            }

            $record->forceFill(['shop_id' => $shopId])->save();
        }

        if (str_contains($eventType, 'order') || str_contains($eventType, 'payment')) {
            $this->recordTransaction(
                $shopId,
                'lemon_squeezy',
                $eventId,
                $eventType,
                data_get($attributes, 'order_number', data_get($attributes, 'id')),
                $this->normalizePaymentStatus(data_get($attributes, 'status', $eventType)),
                $this->planFromMetadataOrPrice($metadata, data_get($attributes, 'variant_name'), data_get($attributes, 'product_name')),
                (float) (data_get($attributes, 'total') / 100),
                (float) (data_get($attributes, 'tax') / 100),
                strtoupper((string) data_get($attributes, 'currency', 'EUR')),
                (string) data_get($metadata, 'country', data_get($attributes, 'billing_address.country', 'DE')),
                $decoded,
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $object
     * @param  array<string, mixed>  $decoded
     */
    private function handleStripe(
        string $eventType,
        mixed $object,
        array $metadata,
        ?int $shopId,
        EuroCrossWebhook $record,
        string $eventId,
        array $decoded,
    ): void {
        $settings = $shopId !== null ? EuroCrossSettings::find($shopId) : null;

        if (str_starts_with($eventType, 'customer.subscription')) {
            /** @var array<string, mixed> $object */
            $object = is_array($object) ? $object : [];
            $subscriptionId = (string) data_get($object, 'id', '');
            $status = (string) data_get($object, 'status', '');
            $plan = $this->planFromMetadataOrPrice($metadata, null, null);
            $periodEnd = $this->parseUnixTimestamp(data_get($object, 'current_period_end'));
            $customerId = (string) data_get($object, 'customer', '');
            $email = (string) data_get($object, 'metadata.email', data_get($metadata, 'email', ''));

            if ($settings !== null) {
                $oldValues = $settings->only(['plan', 'subscription_status', 'subscription_id', 'subscription_provider']);

                $settings->forceFill([
                    'plan' => $plan !== '' ? $plan : $settings->plan,
                    'subscription_provider' => 'stripe',
                    'subscription_id' => $subscriptionId !== '' ? $subscriptionId : $settings->subscription_id,
                    'subscription_status' => $this->normalizeStripeStatus($status),
                    'subscription_current_period_end' => $periodEnd,
                    'customer_id' => $customerId !== '' ? $customerId : $settings->customer_id,
                    'billing_email' => $email !== '' ? $email : $settings->billing_email,
                    'trial' => false,
                    'trial_expires' => null,
                    'grace_expires' => null,
                ])->save();

                $this->audit($settings, 'webhook_subscription_updated', $oldValues, [
                    'provider' => 'stripe',
                    'event' => $eventType,
                    'status' => $settings->subscription_status,
                    'plan' => $settings->plan,
                ], $record);
            }

            $record->forceFill(['shop_id' => $shopId])->save();
        }

        if ($eventType === 'checkout.session.completed') {
            /** @var array<string, mixed> $object */
            $object = is_array($object) ? $object : [];
            $subscriptionId = (string) data_get($object, 'subscription', '');
            $customerId = (string) data_get($object, 'customer', '');
            $email = (string) data_get($object, 'customer_email', data_get($object, 'customer_details.email', ''));
            $plan = $this->planFromMetadataOrPrice($metadata, null, null);

            if ($settings !== null) {
                $settings->forceFill([
                    'plan' => $plan !== '' ? $plan : $settings->plan,
                    'subscription_provider' => 'stripe',
                    'subscription_id' => $subscriptionId !== '' ? $subscriptionId : $settings->subscription_id,
                    'subscription_status' => 'active',
                    'customer_id' => $customerId !== '' ? $customerId : $settings->customer_id,
                    'billing_email' => $email !== '' ? $email : $settings->billing_email,
                    'trial' => false,
                    'trial_expires' => null,
                    'grace_expires' => null,
                ])->save();
            }
        }

        if (str_starts_with($eventType, 'invoice.payment')) {
            /** @var array<string, mixed> $object */
            $object = is_array($object) ? $object : [];
            $this->recordTransaction(
                $shopId,
                'stripe',
                $eventId,
                $eventType,
                data_get($object, 'payment_intent', data_get($object, 'id')),
                $this->normalizePaymentStatus(data_get($object, 'status', $eventType)),
                $this->planFromMetadataOrPrice($metadata, null, null),
                (float) (data_get($object, 'amount_paid', data_get($object, 'amount_due', 0)) / 100),
                (float) (data_get($object, 'tax', 0) / 100),
                strtoupper((string) data_get($object, 'currency', 'EUR')),
                (string) data_get($object, 'customer_address.country', data_get($metadata, 'country', 'DE')),
                $decoded,
            );
        }
    }

    private function planFromGumroadPermalink(string $permalink, array $metadata): string
    {
        $plan = (string) ($metadata['plan'] ?? '');

        if (in_array($plan, ['starter', 'growth', 'scale'], true)) {
            return $plan;
        }

        foreach (['starter', 'growth', 'scale'] as $candidate) {
            $configured = config("payment.gumroad.product_{$candidate}");
            if ($configured !== null && (string) $configured === $permalink) {
                return $candidate;
            }
        }

        return 'starter';
    }

    /** @param array<string, mixed> $metadata */
    private function planFromMetadataOrPrice(array $metadata, mixed $variantName, mixed $productName): string
    {
        $plan = (string) data_get($metadata, 'plan', '');

        if (in_array($plan, ['starter', 'growth', 'scale'], true)) {
            return $plan;
        }

        $haystack = strtolower(trim((string) $variantName.' '.(string) $productName));

        foreach (['scale', 'growth', 'starter'] as $candidate) {
            if ($haystack !== '' && str_contains($haystack, $candidate)) {
                return $candidate;
            }
        }

        return 'starter';
    }

    private function normalizeLemonSqueezyStatus(string $status): string
    {
        $status = strtolower($status);

        return match (true) {
            str_contains($status, 'cancel') => 'canceled',
            str_contains($status, 'past_due') => 'past_due',
            str_contains($status, 'active') => 'active',
            str_contains($status, 'trial') => 'trialing',
            default => $status !== '' ? $status : 'active',
        };
    }

    private function normalizeStripeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'active' => 'active',
            'trialing' => 'trialing',
            'past_due' => 'past_due',
            'canceled', 'cancelled' => 'canceled',
            'unpaid' => 'unpaid',
            default => $status !== '' ? $status : 'active',
        };
    }

    private function normalizePaymentStatus(mixed $status): string
    {
        $status = strtolower((string) $status);

        return match (true) {
            str_contains($status, 'paid'), str_contains($status, 'succeeded'), str_contains($status, 'success') => 'paid',
            str_contains($status, 'fail'), str_contains($status, 'declined') => 'failed',
            str_contains($status, 'refund') => 'refunded',
            str_contains($status, 'cancel') => 'canceled',
            default => $status !== '' ? $status : 'paid',
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

    private function parseUnixTimestamp(mixed $value): ?\DateTimeInterface
    {
        if (! is_numeric($value)) {
            return null;
        }

        try {
            return (new \DateTimeImmutable)->setTimestamp((int) $value);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function recordTransaction(
        ?int $shopId,
        string $provider,
        string $eventId,
        string $eventType,
        mixed $transactionId,
        string $status,
        string $plan,
        float $amount,
        float $vatAmount,
        string $currency,
        string $country,
        array $payload,
    ): void {
        EuroCrossTransaction::updateOrCreate(
            ['provider' => $provider, 'event_id' => $eventId],
            [
                'shop_id' => $shopId,
                'transaction_id' => $transactionId !== null ? (string) $transactionId : null,
                'type' => $eventType,
                'status' => $status,
                'plan' => $plan,
                'amount' => max(0, $amount),
                'vat_amount' => max(0, $vatAmount),
                'currency' => $currency,
                'country' => strtoupper(substr($country, 0, 2)),
                'payload' => $payload,
                'occurred_at' => now(),
            ],
        );
    }

    /** @param array<string, mixed>|null $oldValues */
    private function audit(EuroCrossSettings $settings, string $action, ?array $oldValues, array $newValues, EuroCrossWebhook $record): void
    {
        EuroCrossAuditLog::create([
            'shop_id' => $settings->id,
            'action' => $action,
            'entity_type' => 'eurocross_webhooks',
            'entity_id' => $record->id,
            'old_value' => $oldValues === null ? null : json_encode($oldValues),
            'new_value' => json_encode($newValues),
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }
}
