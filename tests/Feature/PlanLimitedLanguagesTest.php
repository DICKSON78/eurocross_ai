<?php

namespace Tests\Feature;

use App\Models\EuroCrossApiKey;
use App\Models\EuroCrossSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitedLanguagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_plan_exposes_only_three_languages(): void
    {
        $this->authenticatePlan('starter', 'ecx_live_starter_token');

        $this->getJson('/eurocross/languages')
            ->assertOk()
            ->assertJsonPath('meta.plan', 'starter')
            ->assertJsonPath('meta.limits.languages', 3)
            ->assertJsonPath('meta.limits.chatbot', false)
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(15, 'all_languages')
            ->assertJsonPath('data.0.code', 'en')
            ->assertJsonPath('data.2.code', 'de');
    }

    public function test_growth_plan_exposes_five_languages(): void
    {
        $this->authenticatePlan('growth', 'ecx_live_growth_token');

        $this->getJson('/eurocross/languages')
            ->assertOk()
            ->assertJsonPath('meta.plan', 'growth')
            ->assertJsonPath('meta.limits.languages', 5)
            ->assertJsonPath('meta.limits.chatbot', true)
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

    public function test_translation_rejects_language_outside_connected_plan(): void
    {
        $this->authenticatePlan('starter', 'ecx_live_reject_token');

        $this->postJson('/eurocross/translate', [
            'text' => 'Hello world',
            'target_lang' => 'es',
        ])->assertStatus(422)->assertJsonValidationErrors('target_lang');
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
}
