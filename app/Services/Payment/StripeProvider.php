<?php

namespace App\Services\Payment;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class StripeProvider implements MorProvider
{
    private string $baseUrl = 'https://api.stripe.com/v1';

    private int $webhookTolerance = 300;

    public function isConfigured(): bool
    {
        return filled(config('payment.stripe.secret'));
    }

    public function supportsPlan(string $plan): bool
    {
        return $this->isConfigured();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createCheckout(array $data): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Stripe is not configured.'];
        }

        $amount = round((float) ($data['amount'] ?? 0) * 100);
        $currency = strtolower((string) ($data['currency'] ?? 'eur'));
        $priceId = $this->priceId((string) ($data['plan'] ?? ''));

        $payload = [
            'mode' => 'subscription',
            'success_url' => $data['success_url'] ?? url('/?checkout=success'),
            'cancel_url' => $data['cancel_url'] ?? url('/?checkout=cancel'),
            'client_reference_id' => (string) data_get($data, 'metadata.shop_id', ''),
            'metadata' => $data['metadata'] ?? [],
            'subscription_data[metadata]' => $data['metadata'] ?? [],
        ];

        if (! empty($data['email'])) {
            $payload['customer_email'] = $data['email'];
        }

        if ($priceId !== null) {
            $payload['line_items[0][price]'] = $priceId;
        } else {
            if ($amount <= 0) {
                return ['success' => false, 'error' => 'A Stripe price or amount is required.'];
            }

            $payload['line_items[0][price_data][currency]'] = $currency;
            $payload['line_items[0][price_data][unit_amount]'] = $amount;
            $payload['line_items[0][price_data][product_data][name]'] = $data['name'] ?? 'EuroCross AI Subscription';
            $payload['line_items[0][price_data][recurring][interval]'] = 'month';
        }

        $payload['line_items[0][quantity]'] = 1;

        try {
            $response = $this->request()->withHeaders([
                'Idempotency-Key' => (string) ($data['idempotency_key'] ?? Str::uuid()),
            ])->asForm()->post($this->baseUrl.'/checkout/sessions', $payload);

            if (! $response->successful()) {
                Log::channel('api')->error('Stripe checkout failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['success' => false, 'error' => 'Stripe checkout could not be created.'];
            }

            return [
                'success' => true,
                'checkout_url' => (string) $response->json('url', ''),
                'session_id' => (string) $response->json('id', ''),
            ];
        } catch (Throwable $exception) {
            Log::channel('api')->error('Stripe API error', ['error' => $exception->getMessage()]);

            return ['success' => false, 'error' => 'Stripe is temporarily unavailable.'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscription(string $id): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Stripe is not configured.'];
        }

        try {
            $response = $this->request()->get($this->baseUrl.'/subscriptions/'.rawurlencode($id));

            if (! $response->successful()) {
                return ['success' => false, 'error' => 'Subscription not found.'];
            }

            /** @var array<string, mixed> $subscription */
            $subscription = $response->json();

            return ['success' => true, 'subscription' => $subscription];
        } catch (Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function changePlan(string $subscriptionId, string $newPlan): array
    {
        $priceId = $this->priceId($newPlan);

        if (! $this->isConfigured() || $priceId === null) {
            return ['success' => false, 'error' => 'Stripe is not configured for this plan.'];
        }

        try {
            $current = $this->getSubscription($subscriptionId);

            if (! ($current['success'] ?? false)) {
                return $current;
            }

            /** @var array<string, mixed> $subscription */
            $subscription = $current['subscription'];
            /** @var array<int, array<string, mixed>> $items */
            $items = data_get($subscription, 'items.data', []);

            if ($items === []) {
                return ['success' => false, 'error' => 'Subscription has no items to update.'];
            }

            $response = $this->request()->asForm()->post(
                $this->baseUrl.'/subscriptions/'.rawurlencode($subscriptionId),
                [
                    'items[0][id]' => (string) $items[0]['id'],
                    'items[0][price]' => $priceId,
                    'proration_behavior' => 'create_prorations',
                ],
            );

            if (! $response->successful()) {
                return ['success' => false, 'error' => 'Plan change failed.'];
            }

            /** @var array<string, mixed> $updated */
            $updated = $response->json();

            return ['success' => true, 'subscription' => $updated];
        } catch (Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $subscriptionId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Stripe is not configured.'];
        }

        try {
            $response = $this->request()->delete($this->baseUrl.'/subscriptions/'.rawurlencode($subscriptionId));

            if (! $response->successful()) {
                return ['success' => false, 'error' => 'Cancellation failed.'];
            }

            /** @var array<string, mixed> $subscription */
            $subscription = $response->json();

            return ['success' => true, 'subscription' => $subscription];
        } catch (Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    public function portalUrl(string $customerId): string
    {
        if (! $this->isConfigured()) {
            return '';
        }

        try {
            $response = $this->request()->asForm()->post($this->baseUrl.'/billing_portal/sessions', [
                'customer' => $customerId,
                'return_url' => url('/?billing=portal'),
            ]);

            if (! $response->successful()) {
                return '';
            }

            return (string) $response->json('url', '');
        } catch (Throwable $exception) {
            Log::channel('api')->error('Stripe portal session failed', ['error' => $exception->getMessage()]);

            return '';
        }
    }

    public function verifyWebhook(string $payload, string $signature): bool
    {
        $secret = (string) config('payment.stripe.webhook_secret');

        if ($secret === '' || $signature === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signature) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = (int) ($parts['t'] ?? 0);
        $providedSignatures = array_values(array_filter(
            array_map(
                fn (string $segment): string => $segment === 'v1' ? (string) ($parts['v1'] ?? '') : '',
                array_keys($parts),
            ),
        ));

        if ($timestamp === 0 || abs(now()->getTimestamp() - $timestamp) > $this->webhookTolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        if ($providedSignatures === []) {
            $providedSignatures = [$parts['v1'] ?? ''];
        }

        foreach ($providedSignatures as $provided) {
            if ($provided !== '' && hash_equals($expected, $provided)) {
                return true;
            }
        }

        return false;
    }

    public function priceId(string $plan): ?string
    {
        $value = config("payment.stripe.price_{$plan}");

        return filled($value) ? (string) $value : null;
    }

    private function request(): PendingRequest
    {
        return Http::connectTimeout(3)
            ->timeout(15)
            ->withToken((string) config('payment.stripe.secret'))
            ->acceptJson();
    }
}
