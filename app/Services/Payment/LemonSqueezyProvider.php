<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class LemonSqueezyProvider implements MorProvider
{
    private string $baseUrl = 'https://api.lemonsqueezy.com/v1';

    public function isConfigured(): bool
    {
        return filled(config('payment.lemon_squeezy.key'))
            && filled(config('payment.lemon_squeezy.store_id'));
    }

    public function supportsPlan(string $plan): bool
    {
        return $this->isConfigured() && filled($this->variantId($plan));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createCheckout(array $data): array
    {
        $validation = Validator::make($data, [
            'plan' => ['required', 'string', 'in:starter,growth,scale'],
            'email' => ['required', 'email'],
        ]);

        if ($validation->fails()) {
            return ['success' => false, 'error' => 'Invalid checkout data: '.$validation->errors()->first()];
        }

        if (! $this->supportsPlan((string) $data['plan'])) {
            return ['success' => false, 'error' => 'Lemon Squeezy is not configured for this plan.'];
        }

        $payload = [
            'data' => [
                'type' => 'checkouts',
                'attributes' => [
                    'variant_id' => (string) ($data['variant_id'] ?? $this->variantId((string) $data['plan'])),
                    'email' => $data['email'],
                    'name' => $data['name'] ?? 'EuroCross AI '.ucfirst((string) $data['plan']),
                    'custom_data' => $data['metadata'] ?? [],
                    'checkout_options' => [
                        'embed' => false,
                        'media' => false,
                        'logo' => true,
                    ],
                ],
                'relationships' => [
                    'store' => [
                        'data' => [
                            'type' => 'stores',
                            'id' => (string) config('payment.lemon_squeezy.store_id'),
                        ],
                    ],
                ],
            ],
        ];

        if (! empty($data['success_url'])) {
            $payload['data']['attributes']['checkout_options']['success_url'] = $data['success_url'];
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('payment.lemon_squeezy.key'),
                    'Accept' => 'application/vnd.api+json',
                    'Content-Type' => 'application/vnd.api+json',
                ])
                ->post($this->baseUrl.'/checkouts', $payload);

            if (! $response->successful()) {
                Log::channel('api')->error('Lemon Squeezy checkout failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['success' => false, 'error' => 'Lemon Squeezy checkout could not be created.'];
            }

            $url = $response->json('data.attributes.url');

            if (! is_string($url) || $url === '') {
                return ['success' => false, 'error' => 'Lemon Squeezy did not return a checkout URL.'];
            }

            return [
                'success' => true,
                'checkout_url' => $url,
                'checkout_id' => (string) $response->json('data.id', ''),
            ];
        } catch (Throwable $exception) {
            Log::channel('api')->error('Lemon Squeezy API error', ['error' => $exception->getMessage()]);

            return ['success' => false, 'error' => 'Lemon Squeezy is temporarily unavailable.'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscription(string $id): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Lemon Squeezy is not configured.'];
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('payment.lemon_squeezy.key'),
                    'Accept' => 'application/vnd.api+json',
                ])
                ->get($this->baseUrl.'/subscriptions/'.rawurlencode($id));

            if (! $response->successful()) {
                return ['success' => false, 'error' => 'Subscription not found.'];
            }

            return ['success' => true, 'subscription' => $response->json('data')];
        } catch (Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function changePlan(string $subscriptionId, string $newPlan): array
    {
        if (! $this->supportsPlan($newPlan)) {
            return ['success' => false, 'error' => 'Lemon Squeezy is not configured for this plan.'];
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('payment.lemon_squeezy.key'),
                    'Accept' => 'application/vnd.api+json',
                    'Content-Type' => 'application/vnd.api+json',
                ])
                ->patch($this->baseUrl.'/subscriptions/'.rawurlencode($subscriptionId), [
                    'data' => [
                        'type' => 'subscriptions',
                        'id' => $subscriptionId,
                        'attributes' => [
                            'variant_id' => (string) $this->variantId($newPlan),
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return ['success' => false, 'error' => 'Plan change failed.'];
            }

            return ['success' => true, 'subscription' => $response->json('data')];
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
            return ['success' => false, 'error' => 'Lemon Squeezy is not configured.'];
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('payment.lemon_squeezy.key'),
                    'Accept' => 'application/vnd.api+json',
                ])
                ->delete($this->baseUrl.'/subscriptions/'.rawurlencode($subscriptionId));

            if (! $response->successful()) {
                return ['success' => false, 'error' => 'Cancellation failed.'];
            }

            return ['success' => true, 'message' => 'Subscription cancelled.'];
        } catch (Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    public function portalUrl(string $customerId): string
    {
        return 'https://app.lemonsqueezy.com/customers/'.rawurlencode($customerId);
    }

    public function verifyWebhook(string $payload, string $signature): bool
    {
        $secret = (string) config('payment.lemon_squeezy.webhook_secret');

        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    public function variantId(string $plan): ?string
    {
        $value = config("payment.lemon_squeezy.variant_{$plan}");

        return filled($value) ? (string) $value : null;
    }
}
