<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Payment\GumroadProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class BillingController extends Controller
{
    public function checkout(string $plan, GumroadProvider $gumroad): RedirectResponse
    {
        abort_unless(in_array($plan, ['starter', 'growth', 'scale'], true), 404);

        /** @var User $user */
        $user = Auth::user();
        $settings = $user->euroCrossSettings()->firstOrCreate([], [
            'billing_email' => $user->email,
            'locale' => 'en',
            'plan' => 'starter',
            'trial' => true,
            'trial_started_at' => now(),
            'trial_expires' => now()->addDays((int) config('payment.trial.days', 7)),
            'grace_expires' => now()->addDays((int) config('payment.trial.days', 7) + 3),
        ]);

        if (blank($settings->billing_email)) {
            $settings->forceFill(['billing_email' => $user->email])->save();
        }

        if ($settings->hasActiveSubscription()) {
            $message = $settings->plan === $plan
                ? 'You already have an active subscription for this plan.'
                : 'You already have an active subscription. To change plans, cancel your current Gumroad subscription and subscribe to the new plan after it ends.';

            return redirect()->route('dashboard.module', 'billing')->withErrors(['billing' => $message]);
        }

        abort_unless($gumroad->supportsPlan($plan), 503, 'Payment provider is not configured for this plan.');

        $url = $gumroad->createCheckout([
            'plan' => $plan,
            'email' => $settings->billing_email ?: $user->email,
            'metadata' => [
                'shop_id' => (string) $settings->id,
                'plan' => $plan,
            ],
        ])['checkout_url'] ?? null;

        abort_if($url === null, 502, 'Could not create checkout URL.');

        return redirect()->away($url);
    }
}
