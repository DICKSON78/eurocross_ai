<?php

namespace Tests\Feature;

use App\Models\EuroCrossApiKey;
use App\Models\EuroCrossSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitedLanguagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_starter_plan_exposes_ten_languages(): void
    {
        $this->authenticatePlan('starter', 'ecx_live_starter_token');

        $this->getJson('/eurocross/languages')
            ->assertOk()
            ->assertJsonPath('meta.plan', 'starter')
            ->assertJsonPath('meta.status', 'trial')
            ->assertJsonPath('meta.limits.languages', 10)
            ->assertJsonPath('meta.limits.chatbot', false)
            ->assertJsonCount(10, 'data')
            ->assertJsonCount(15, 'all_languages')
            ->assertJsonPath('data.0.code', 'en')
            ->assertJsonPath('data.9.code', 'ja');
    }

    public function test_trial_growth_plan_exposes_ten_languages(): void
    {
        $this->authenticatePlan('growth', 'ecx_live_growth_token');

        $this->getJson('/eurocross/languages')
            ->assertOk()
            ->assertJsonPath('meta.plan', 'growth')
            ->assertJsonPath('meta.status', 'trial')
            ->assertJsonPath('meta.limits.languages', 10)
            ->assertJsonPath('meta.limits.chatbot', true)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.9.code', 'ja');
    }

    public function test_active_starter_subscription_exposes_three_languages(): void
    {
        $this->authenticateActiveSubscription('starter', 'ecx_live_paid_starter_token');

        $this->getJson('/eurocross/languages')
            ->assertOk()
            ->assertJsonPath('meta.plan', 'starter')
            ->assertJsonPath('meta.limits.languages', 3)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.2.code', 'de');
    }

    public function test_active_growth_subscription_exposes_five_languages(): void
    {
        $this->authenticateActiveSubscription('growth', 'ecx_live_paid_growth_token');

        $this->getJson('/eurocross/languages')
            ->assertOk()
            ->assertJsonPath('meta.plan', 'growth')
            ->assertJsonPath('meta.limits.languages', 5)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.4.code', 'it');
    }

    public function test_scale_plan_exposes_all_fifteen_languages(): void
    {
        $this->authenticatePlan('scale', 'ecx_live_scale_token');

        $this->getJson('/eurocross/languages')
            ->assertOk()
            ->assertJsonPath('meta.plan', 'scale')
            ->assertJsonPath('meta.limits.languages', 15)
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('data.14.code', 'el');
    }

    public function test_translation_rejects_language_outside_trial_limit(): void
    {
        $this->authenticatePlan('starter', 'ecx_live_reject_token');

        $this->postJson('/eurocross/translate', [
            'text' => 'Hello world',
            'target_lang' => 'sv',
        ])->assertStatus(422)->assertJsonValidationErrors('target_lang');
    }

    public function test_api_key_in_query_string_returns_401(): void
    {
        $settings = EuroCrossSettings::factory()->create();
        EuroCrossApiKey::factory()
            ->for($settings, 'shop')
            ->accessToken('ecx_live_query_token')
            ->create();

        $this->getJson('/eurocross/settings?api_key=ecx_live_query_token')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'A valid X-API-Key header is required.');
    }

    private function authenticatePlan(string $plan, string $token): void
    {
        $settings = EuroCrossSettings::factory()->create(['plan' => $plan]);

        EuroCrossApiKey::factory()
            ->for($settings, 'shop')
            ->accessToken($token)
            ->create();

        $this->withHeaders(['X-API-Key' => $token]);
    }

    private function authenticateActiveSubscription(string $plan, string $token): void
    {
        $settings = EuroCrossSettings::factory()->activeSubscription($plan)->create();

        EuroCrossApiKey::factory()
            ->for($settings, 'shop')
            ->accessToken($token)
            ->create();

        $this->withHeaders(['X-API-Key' => $token]);
    }
}
