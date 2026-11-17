<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Throwable;

class GumroadProvider implements MorProvider
{
    private string $baseUrl = 'https://api.gumroad.com/v2';

    public function isConfigured(): bool
    {
        return filled(config('payment.gumroad.seller_id')) || filled(config('payment.gumroad.access_token'));
    }

    public function supportsPlan(string $plan): bool
    {
        return $this->isConfigured() && (filled($this->productPermalink($plan)) || filled($this->checkoutUrl($plan)));
    }

    /** @param array<string, mixed> $data */
    public function createCheckout(array $data): array
    {
        $plan = (string) ($data['plan'] ?? '');
        $configuredUrl = $this->checkoutUrl($plan);
        $permalink = $this->productPermalink($plan);

        if ($configuredUrl === null && $permalink === null) {
            return ['success' => false, 'error' => 'Gumroad is not configured for this plan.'];
        }

        $url = $configuredUrl ?? 'https://gumroad.com/l/'.rawurlencode((string) $permalink);

        $query = [];
        if (! empty($data['email'])) {
            $query['email'] = $data['email'];
        }
        $query['wanted'] = 'true';
        if (! empty($data['metadata'])) {
            foreach ($data['metadata'] as $key => $value) {
                $query['custom_fields['.$key.']'] = (string) $value;
            }
        }

        if (str_contains($url, '?')) {
            $url .= '&'.http_build_query($query);
        } else {
            $url .= '?'.http_build_query($query);
        }

        return [
            'success' => true,
            'checkout_url' => $url,
            'checkout_id' => $permalink,
        ];
    }

    public function getSubscription(string $id): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Gumroad is not configured.'];
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(15)
                ->get($this->baseUrl.'/subscriptions/'.rawurlencode($id), [
                    'access_token' => config('payment.gumroad.access_token'),
                ]);

            if (! $response->successful()) {
                return ['success' => false, 'error' => 'Subscription not found.'];
            }

            return ['success' => true, 'subscription' => $response->json('subscription', [])];
        } catch (Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    public function changePlan(string $subscriptionId, string $newPlan): array
    {
        return ['success' => false, 'error' => 'Plan changes are not supported via Gumroad API. Cancel and resubscribe.'];
    }

    public function cancel(string $subscriptionId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Gumroad is not configured.'];
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(15)
                ->put($this->baseUrl.'/subscriptions/'.rawurlencode($subscriptionId).'/cancel', [
                    'access_token' => config('payment.gumroad.access_token'),
                ]);

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
        return 'https://gumroad.com/library';
    }

    public function verifyWebhook(string $payload, string $signature): bool
    {
        // Gumroad does not send an HMAC signature by default; verify by re-fetching
        // the sale from the API inside the controller instead.
        return true;
    }

    public function productPermalink(string $plan): ?string
    {
        $value = config("payment.gumroad.product_{$plan}");

        return filled($value) ? (string) $value : null;
    }

    public function productId(string $plan): ?string
    {
        $value = config("payment.gumroad.product_id_{$plan}");

        return filled($value) ? (string) $value : null;
    }

    public function checkoutUrl(string $plan): ?string
    {
        $value = config("payment.gumroad.checkout_{$plan}");

        return filled($value) ? (string) $value : null;
    }

    /** @return array<string, string> */
    public function productIdToPlanMap(): array
    {
        $map = [];
        foreach (['starter', 'growth', 'scale'] as $plan) {
            $id = $this->productId($plan);
            if ($id !== null) {
                $map[$id] = $plan;
            }
        }

        return $map;
    }

    public function planFromProductId(?string $productId): ?string
    {
        if ($productId === null || $productId === '') {
            return null;
        }

        return $this->productIdToPlanMap()[$productId] ?? null;
    }

    public function planFromPermalink(?string $permalink): ?string
    {
        if ($permalink === null || $permalink === '') {
            return null;
        }

        foreach (['starter', 'growth', 'scale'] as $plan) {
            if ($this->productPermalink($plan) === $permalink) {
                return $plan;
            }
        }

        return null;
    }
}
