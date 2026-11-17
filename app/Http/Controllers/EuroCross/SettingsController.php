<?php

namespace App\Http\Controllers\EuroCross;

use App\Http\Controllers\Controller;
use App\Models\EuroCrossAuditLog;
use App\Models\EuroCrossSettings;
use App\Models\EuroCrossVatSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $vatSettings = EuroCrossVatSettings::firstOrCreate(
            ['shop_id' => $settings->id],
            ['default_country' => 'TZ', 'viies_status' => 'pending'],
        );

        return response()->json([
            'success' => true,
            'data' => $this->settingsPayload($settings, $vatSettings),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $settings = $this->shop($request);

        $validated = $request->validate([
            'locale' => ['sometimes', 'string', 'in:en,fr,de,es,it,pt,nl,ar,zh,ja'],
            'billing_email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        if ($request->has('plan') && $request->string('plan')->toString() !== $settings->plan) {
            return response()->json([
                'success' => false,
                'error' => 'Plan changes are completed through checkout or a provider webhook.',
            ], 422);
        }

        $oldValues = $settings->only(['locale', 'billing_email']);
        $settings->fill($validated);
        $settings->save();

        $this->audit(
            $settings,
            'settings_updated',
            $oldValues,
            $settings->only(['locale', 'billing_email']),
            $request,
        );

        return response()->json([
            'success' => true,
            'data' => $this->settingsPayload($settings),
            'message' => 'Settings updated.',
        ]);
    }

    public function startTrial(Request $request): JsonResponse
    {
        $settings = $this->shop($request);

        if ($settings->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'error' => 'An active subscription is already in place.',
            ], 400);
        }

        if ($settings->isTrial()) {
            return response()->json([
                'success' => false,
                'error' => 'A trial is already active.',
            ], 400);
        }

        $trialDays = (int) config('payment.trial.days', 14);
        $graceDays = (int) config('payment.trial.grace_period_days', 3);
        $oldValues = $settings->only(['trial', 'trial_started_at', 'trial_expires', 'grace_expires']);

        $settings->forceFill([
            'trial' => true,
            'trial_started_at' => now(),
            'trial_expires' => now()->addDays($trialDays),
            'grace_expires' => now()->addDays($trialDays + $graceDays),
            'subscription_status' => 'trialing',
        ])->save();

        $this->audit($settings, 'trial_started', $oldValues, [
            'trial_expires' => $settings->trial_expires?->toIso8601String(),
            'grace_expires' => $settings->grace_expires?->toIso8601String(),
        ], $request);

        return response()->json([
            'success' => true,
            'message' => 'Trial started.',
            'data' => $this->settingsPayload($settings),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(EuroCrossSettings $settings, ?EuroCrossVatSettings $vatSettings = null): array
    {
        $vatSettings ??= $settings->vatSetting;

        return [
            'settings' => array_merge($settings->only([
                'id',
                'locale',
                'plan',
                'active_accounts',
                'monthly_recurring_revenue',
                'monthly_profit',
                'billing_email',
            ]), [
                'plan_name' => $settings->getPlanDisplayName(),
                'plan_price' => $settings->planPrice(),
                'plan_features' => array_values($settings->getPlanFeatures()),
                'trial' => [
                    'active' => $settings->isTrial(),
                    'days_remaining' => $settings->trialRemainingDays(),
                    'expires_at' => $settings->trial_expires?->toIso8601String(),
                    'grace_ends_at' => $settings->gracePeriodEndsAt()?->toIso8601String(),
                ],
                'subscription' => [
                    'status' => $settings->subscription_status,
                    'provider' => $settings->subscription_provider,
                    'id' => $settings->subscription_id,
                    'current_period_end' => $settings->subscription_current_period_end?->toIso8601String(),
                ],
                'access' => $settings->accessStatus(),
            ]),
            'vat_settings' => $vatSettings?->only([
                'id',
                'default_country',
                'vat_number',
                'use_oss',
                'viies_status',
                'viies_last_checked',
                'foreign_vat_rate',
            ]),
            'plan_features' => array_values($settings->getPlanFeatures()),
            'plan_feature_details' => $settings->getPlanFeatures(),
            'is_trial' => $settings->isTrial(),
            'trial_days_remaining' => $settings->trialRemainingDays(),
            'plan_price' => $settings->planPrice(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function audit(
        EuroCrossSettings $settings,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        Request $request,
    ): void {
        EuroCrossAuditLog::create([
            'shop_id' => $settings->id,
            'action' => $action,
            'entity_type' => 'eurocross_settings',
            'entity_id' => $settings->id,
            'old_value' => $oldValues === null ? null : json_encode($oldValues),
            'new_value' => $newValues === null ? null : json_encode($newValues),
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
