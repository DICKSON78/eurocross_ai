<?php

namespace App\Http\Controllers\EuroCross;

use App\Http\Controllers\Controller;
use App\Models\EuroCrossAuditLog;
use App\Models\EuroCrossSettings;
use App\Services\Payment\GumroadProvider;
use App\Services\Payment\LemonSqueezyProvider;
use App\Services\Payment\MorProvider;
use App\Services\Payment\StripeProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private GumroadProvider $gumroad,
        private LemonSqueezyProvider $lemonSqueezy,
        private StripeProvider $stripe,
    ) {}

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:starter,growth,scale'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $settings = $this->shop($request);
        $plan = (string) $validated['plan'];

        if ($settings->hasActiveSubscription()) {
            $message = $settings->plan === $plan
                ? 'You already have an active subscription for this plan.'
                : 'You already have an active subscription. Cancel the current subscription before purchasing a different plan.';

            return response()->json([
                'success' => false,
                'error' => $message,
            ], 409);
        }

        [$providerName, $provider] = $this->resolveProvider($plan);

        if ($provider === null) {
            return response()->json([
                'success' => false,
                'error' => 'No payment provider is configured. Set Lemon Squeezy or Stripe credentials first.',
            ], 503);
        }

        $planConfig = config("payment.plans.{$plan}", []);
        $metadata = [
            'shop_id' => (string) $settings->id,
            'plan' => $plan,
            'email' => $validated['email'],
        ];

        $data = [
            'plan' => $plan,
            'name' => 'EuroCross AI '.ucfirst($plan),
            'description' => 'EuroCross AI '.ucfirst($plan).' monthly subscription',
            'email' => $validated['email'],
            'amount' => (float) ($planConfig['price'] ?? 0),
            'currency' => $planConfig['currency'] ?? 'eur',
            'metadata' => $metadata,
            'success_url' => url('/?checkout=success'),
            'cancel_url' => url('/?checkout=cancel'),
        ];

        $result = $provider->createCheckout($data);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Checkout creation failed.',
            ], 502);
        }

        $settings->forceFill([
            'billing_email' => $validated['email'],
        ])->save();

        $this->audit($settings, 'checkout_started', [
            'plan' => $plan,
            'provider' => $providerName,
            'checkout_id' => $result['checkout_id'] ?? $result['session_id'] ?? null,
        ], $request);

        return response()->json([
            'success' => true,
            'checkout_url' => $result['checkout_url'],
            'provider' => $providerName,
            'checkout_id' => $result['checkout_id'] ?? $result['session_id'] ?? null,
        ]);
    }

    public function subscription(Request $request, string $subscriptionId): JsonResponse
    {
        $settings = $this->shop($request);
        [$providerName, $provider] = $this->providerFor($request, $settings);

        if ($provider === null) {
            return response()->json([
                'success' => false,
                'error' => 'No payment provider is configured for this subscription.',
            ], 404);
        }

        $result = $provider->getSubscription($subscriptionId);

        return response()->json(
            array_merge($result, ['provider' => $providerName]),
            ($result['success'] ?? false) ? 200 : 502,
        );
    }

    public function changePlan(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $validated = $request->validate([
            'subscription_id' => ['sometimes', 'string', 'max:255'],
            'new_plan' => ['required', 'string', 'in:starter,growth,scale'],
            'provider' => ['sometimes', 'string', 'in:lemon_squeezy,stripe'],
        ]);

        $subscriptionId = (string) ($validated['subscription_id'] ?? $settings->subscription_id ?? '');

        if ($subscriptionId === '') {
            return response()->json([
                'success' => false,
                'error' => 'No subscription ID is available.',
            ], 422);
        }

        [$providerName, $provider] = $this->providerFor($request, $settings);

        if ($provider === null) {
            return response()->json([
                'success' => false,
                'error' => 'No payment provider is configured for this subscription.',
            ], 503);
        }

        $result = $provider->changePlan($subscriptionId, (string) $validated['new_plan']);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Plan change failed.',
            ], 502);
        }

        $oldPlan = $settings->plan;
        $settings->forceFill(['plan' => $validated['new_plan']])->save();

        $this->audit($settings, 'plan_changed', [
            'old_plan' => $oldPlan,
            'new_plan' => $settings->plan,
            'provider' => $providerName,
        ], $request);

        return response()->json([
            'success' => true,
            'message' => 'Plan updated.',
            'new_plan' => $settings->plan,
            'provider' => $providerName,
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $validated = $request->validate([
            'subscription_id' => ['sometimes', 'string', 'max:255'],
            'provider' => ['sometimes', 'string', 'in:lemon_squeezy,stripe'],
        ]);

        $subscriptionId = (string) ($validated['subscription_id'] ?? $settings->subscription_id ?? '');

        if ($subscriptionId === '') {
            return response()->json([
                'success' => false,
                'error' => 'No subscription ID is available.',
            ], 422);
        }

        [$providerName, $provider] = $this->providerFor($request, $settings);

        if ($provider === null) {
            return response()->json([
                'success' => false,
                'error' => 'No payment provider is configured for this subscription.',
            ], 503);
        }

        $result = $provider->cancel($subscriptionId);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Cancellation failed.',
            ], 502);
        }

        $settings->forceFill([
            'subscription_status' => 'canceled',
        ])->save();

        $this->audit($settings, 'subscription_canceled', [
            'provider' => $providerName,
            'subscription_id' => $subscriptionId,
        ], $request);

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled.',
        ]);
    }

    public function checkoutRedirect(Request $request, string $plan): RedirectResponse
    {
        abort_unless(in_array($plan, ['starter', 'growth', 'scale'], true), 404);

        $settings = $this->shop($request);
        $email = (string) ($request->query('email', $settings->billing_email ?? ''));
        $provider = $this->gumroad;

        if ($settings->hasActiveSubscription()) {
            $message = $settings->plan === $plan
                ? 'You already have an active subscription for this plan.'
                : 'You already have an active subscription. Cancel the current subscription before purchasing a different plan.';

            abort(409, $message);
        }

        if (! $provider->supportsPlan($plan)) {
            abort(503, 'Payment provider is not configured for this plan.');
        }

        $url = $provider->createCheckout([
            'plan' => $plan,
            'email' => $email,
            'metadata' => [
                'shop_id' => (string) $settings->id,
                'plan' => $plan,
            ],
        ])['checkout_url'] ?? null;

        abort_if($url === null, 502, 'Could not create checkout URL.');

        return redirect()->away($url);
    }

    public function portal(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $validated = $request->validate([
            'customer_id' => ['sometimes', 'string', 'max:255'],
            'provider' => ['sometimes', 'string', 'in:lemon_squeezy,stripe'],
        ]);

        $customerId = (string) ($validated['customer_id'] ?? $settings->customer_id ?? '');

        if ($customerId === '') {
            return response()->json([
                'success' => false,
                'error' => 'No customer ID is available.',
            ], 422);
        }

        [$providerName, $provider] = $this->providerFor($request, $settings);

        if ($provider === null) {
            return response()->json([
                'success' => false,
                'error' => 'No payment provider is configured.',
            ], 503);
        }

        $portalUrl = $provider->portalUrl($customerId);

        return response()->json([
            'success' => $portalUrl !== '',
            'portal_url' => $portalUrl,
            'provider' => $providerName,
        ]);
    }

    /**
     * @return array{0: string|null, 1: MorProvider|null}
     */
    private function resolveProvider(string $plan): array
    {
        $preferred = (string) config('payment.provider', 'gumroad');

        if ($preferred === 'gumroad' && $this->gumroad->supportsPlan($plan)) {
            return ['gumroad', $this->gumroad];
        }

        if ($preferred === 'lemon_squeezy' && $this->lemonSqueezy->supportsPlan($plan)) {
            return ['lemon_squeezy', $this->lemonSqueezy];
        }

        if ($preferred === 'stripe' && $this->stripe->supportsPlan($plan)) {
            return ['stripe', $this->stripe];
        }

        if ($this->gumroad->supportsPlan($plan)) {
            return ['gumroad', $this->gumroad];
        }

        if ($this->lemonSqueezy->supportsPlan($plan)) {
            return ['lemon_squeezy', $this->lemonSqueezy];
        }

        if ($this->stripe->supportsPlan($plan)) {
            return ['stripe', $this->stripe];
        }

        return [null, null];
    }

    /**
     * @return array{0: string|null, 1: MorProvider|null}
     */
    private function providerFor(Request $request, EuroCrossSettings $settings): array
    {
        $name = (string) ($request->input('provider') ?: ($settings->subscription_provider ?? ''));

        return match ($name) {
            'gumroad' => $this->gumroad->isConfigured()
                ? ['gumroad', $this->gumroad]
                : [null, null],
            'lemon_squeezy' => $this->lemonSqueezy->isConfigured()
                ? ['lemon_squeezy', $this->lemonSqueezy]
                : [null, null],
            'stripe' => $this->stripe->isConfigured()
                ? ['stripe', $this->stripe]
                : [null, null],
            default => $this->configuredDefaultProvider(),
        };
    }

    /**
     * @return array{0: string|null, 1: MorProvider|null}
     */
    private function configuredDefaultProvider(): array
    {
        if ($this->gumroad->isConfigured()) {
            return ['gumroad', $this->gumroad];
        }

        if ($this->lemonSqueezy->isConfigured()) {
            return ['lemon_squeezy', $this->lemonSqueezy];
        }

        if ($this->stripe->isConfigured()) {
            return ['stripe', $this->stripe];
        }

        return [null, null];
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    private function audit(EuroCrossSettings $settings, string $action, array $newValues, Request $request): void
    {
        EuroCrossAuditLog::create([
            'shop_id' => $settings->id,
            'action' => $action,
            'entity_type' => 'eurocross_settings',
            'entity_id' => $settings->id,
            'new_value' => json_encode($newValues),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function shop(Request $request): EuroCrossSettings
    {
        $settings = $request->attributes->get('eurocross_settings');

        abort_unless($settings instanceof EuroCrossSettings, 401, 'Authenticated shop context is missing.');

        return $settings;
    }
}
