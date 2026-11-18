<?php

namespace Tests\Feature;

use App\Jobs\ProcessGumroadWebhook;
use App\Models\EuroCrossApiKey;
use App\Models\EuroCrossSettings;
use App\Models\EuroCrossWebhook;
use App\Models\User;
use App\Services\ConnectedSiteService;
use App\Services\Payment\GumroadProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GumroadBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payment.gumroad.webhook_secret', 'test-secret');
        config()->set('payment.gumroad.seller_id', 'SELLER==');
        config()->set('payment.gumroad.product_id_starter', 'GWkd8qsP7GxksJcVdr_oMw==');
        config()->set('payment.gumroad.product_id_growth', '03Ex_UcTD52UiV3lhwHZhA==');
        config()->set('payment.gumroad.product_id_scale', 'fM75G5JEZvgvN1MpprCdcA==');
        config()->set('payment.gumroad.checkout_starter', 'https://gumroad.test/l/starter');
        config()->set('payment.gumroad.checkout_growth', 'https://gumroad.test/l/growth');
        config()->set('payment.gumroad.checkout_scale', 'https://gumroad.test/l/scale');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'event' => 'sale',
            'sale_id' => 'SALE-1',
            'product_id' => 'GWkd8qsP7GxksJcVdr_oMw==',
            'product_permalink' => 'eurocross-starter',
            'email' => 'buyer@example.com',
            'seller_id' => 'SELLER==',
            'subscription_id' => 'SUB-1',
            'next_charge_date' => now()->addMonth()->toIso8601String(),
        ], $overrides);
    }

    private function signedPost(array $payload): TestResponse
    {
        $raw = json_encode($payload);

        return $this->call(
            'POST',
            '/eurocross/webhook/gumroad',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_GUMROAD_SIGNATURE' => hash_hmac('sha256', (string) $raw, 'test-secret'),
            ],
            (string) $raw,
        );
    }

    public function test_valid_signed_webhook_is_accepted_and_queued(): void
    {
        Queue::fake();

        $this->signedPost($this->payload())->assertOk()->assertJson(['success' => true]);

        Queue::assertPushed(ProcessGumroadWebhook::class);
    }

    public function test_valid_signed_webhook_persists_record_before_dispatch(): void
    {
        Queue::fake();
        $payload = $this->payload();
        $signature = hash_hmac('sha256', (string) json_encode($payload), 'test-secret');

        $this->signedPost($payload)->assertOk()->assertJson(['success' => true]);

        $record = EuroCrossWebhook::query()->where('provider', 'gumroad')->firstOrFail();
        $this->assertSame('sale', $record->event);
        $this->assertSame(md5('gumroad|sale|SALE-1|SUB-1|'), $record->event_id);
        $this->assertSame($payload, $record->payload);
        $this->assertSame($signature, $record->signature);
        $this->assertFalse($record->processed);

        Queue::assertPushed(ProcessGumroadWebhook::class, fn (ProcessGumroadWebhook $job): bool => $job->webhookRecordId === $record->id);
    }

    public function test_query_string_secret_is_rejected(): void
    {
        $raw = json_encode($this->payload());

        $this->call(
            'POST',
            '/eurocross/webhook/gumroad?secret=test-secret',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) $raw,
        )->assertForbidden();

        $this->assertDatabaseEmpty('eurocross_webhooks');
    }

    public function test_wrong_signature_is_rejected(): void
    {
        $raw = json_encode($this->payload());

        $this->call(
            'POST',
            '/eurocross/webhook/gumroad',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_GUMROAD_SIGNATURE' => 'bad'],
            (string) $raw,
        )->assertForbidden();
    }

    public function test_wrong_seller_id_is_rejected(): void
    {
        $payload = $this->payload(['seller_id' => 'OTHER==']);
        $raw = json_encode($payload);

        $this->call(
            'POST',
            '/eurocross/webhook/gumroad',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_GUMROAD_SIGNATURE' => hash_hmac('sha256', (string) $raw, 'test-secret'),
            ],
            (string) $raw,
        )->assertForbidden();
    }

    public function test_duplicate_event_is_ignored(): void
    {
        Queue::fake();

        $payload = $this->payload();
        $eventId = md5('gumroad|sale|SALE-1|SUB-1|');
        EuroCrossWebhook::create([
            'provider' => 'gumroad',
            'event_id' => $eventId,
            'event' => 'sale',
            'payload' => json_encode($payload),
            'processed' => true,
            'signature' => '',
        ]);

        $this->signedPost($payload)->assertOk()->assertJson(['status' => 'duplicate']);

        Queue::assertNotPushed(ProcessGumroadWebhook::class);
    }

    public function test_unknown_product_activates_nothing(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $settings = EuroCrossSettings::factory()->create(['user_id' => $user->id, 'plan' => 'starter']);
        Http::fake();

        $job = new ProcessGumroadWebhook($this->payload([
            'product_id' => 'UNKNOWN',
            'product_permalink' => 'unknown',
        ]), 'evt-unknown', 'sale');
        $job->handle(app(GumroadProvider::class));

        $this->assertSame('starter', $settings->fresh()->plan);
    }

    public function test_recurring_charge_extends_plan_and_resets_words(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $settings = EuroCrossSettings::factory()->create([
            'user_id' => $user->id,
            'plan' => 'starter',
            'subscription_status' => 'active',
            'subscription_id' => 'SUB-1',
            'words_reset_at' => now()->subMonth(),
        ]);
        Http::fake([
            'api.gumroad.com/*' => Http::response(['subscriber' => ['subscription_status' => 'active']]),
        ]);

        $job = new ProcessGumroadWebhook($this->payload([
            'event' => 'recurring_charge',
            'product_id' => '03Ex_UcTD52UiV3lhwHZhA==',
            'product_permalink' => 'eurocross-growth',
        ]), 'evt-recurring', 'recurring_charge');
        $job->handle(app(GumroadProvider::class));

        $fresh = $settings->fresh();
        $this->assertSame('growth', $fresh->plan);
        $this->assertSame('active', $fresh->subscription_status);
        $this->assertNotNull($fresh->words_reset_at);
        $this->assertSame('SALE-1', $fresh->gumroad_sale_id);
    }

    public function test_cancellation_keeps_access_until_period_end(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $settings = EuroCrossSettings::factory()->create([
            'user_id' => $user->id,
            'plan' => 'growth',
            'subscription_status' => 'active',
            'subscription_id' => 'SUB-1',
            'subscription_current_period_end' => now()->addDays(10),
        ]);
        Http::fake();

        (new ProcessGumroadWebhook($this->payload(['event' => 'cancellation']), 'evt-cancel', 'cancellation'))
            ->handle(app(GumroadProvider::class));

        $fresh = $settings->fresh();
        $this->assertSame('canceled', $fresh->subscription_status);
        $this->assertTrue($fresh->hasActiveAccess());
    }

    public function test_manual_cancellation_preserves_paid_through_period_end(): void
    {
        config()->set('payment.gumroad.access_token', 'test-gumroad-token');
        $periodEnd = now()->addDays(10);
        $settings = EuroCrossSettings::factory()->activeSubscription()->create([
            'subscription_provider' => 'gumroad',
            'subscription_current_period_end' => $periodEnd,
        ]);
        EuroCrossApiKey::factory()
            ->for($settings, 'shop')
            ->accessToken('ecx_live_cancel_token')
            ->create();
        Http::fake(['api.gumroad.com/*' => Http::response(['success' => true])]);

        $this->withHeaders(['X-API-Key' => 'ecx_live_cancel_token'])
            ->postJson('/eurocross/subscription/cancel')
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $settings->fresh();
        $this->assertSame('canceled', $fresh->subscription_status);
        $this->assertSame($periodEnd->toDateTimeString(), $fresh->subscription_current_period_end->toDateTimeString());
    }

    public function test_refund_suspends_access(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $settings = EuroCrossSettings::factory()->create([
            'user_id' => $user->id,
            'plan' => 'growth',
            'subscription_status' => 'active',
            'subscription_id' => 'SUB-1',
        ]);
        Http::fake();

        (new ProcessGumroadWebhook($this->payload(['event' => 'refund']), 'evt-refund', 'refund'))
            ->handle(app(GumroadProvider::class));

        $this->assertSame('suspended', $settings->fresh()->subscription_status);
    }

    public function test_failed_payment_starts_grace_period(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $settings = EuroCrossSettings::factory()->create([
            'user_id' => $user->id,
            'plan' => 'growth',
            'subscription_status' => 'active',
            'subscription_id' => 'SUB-1',
            'trial' => false,
        ]);
        Http::fake();

        (new ProcessGumroadWebhook($this->payload(['event' => 'payment_failed']), 'evt-failed', 'payment_failed'))
            ->handle(app(GumroadProvider::class));

        $fresh = $settings->fresh();
        $this->assertSame('past_due', $fresh->subscription_status);
        $this->assertTrue($fresh->isInGracePeriod());
        $this->assertTrue($fresh->grace_expires->greaterThan(now()->addDays(2)));
        $this->assertTrue($fresh->grace_expires->lessThanOrEqualTo(now()->addDays(3)));
    }

    public function test_past_due_grace_period_expires_after_three_days(): void
    {
        $settings = EuroCrossSettings::factory()->create([
            'plan' => 'growth',
            'subscription_status' => 'past_due',
            'subscription_id' => 'SUB-1',
            'trial' => false,
            'trial_expires' => now()->subDays(5),
            'grace_expires' => now()->subMinute(),
        ]);

        $this->assertFalse($settings->isInGracePeriod());
        $this->assertFalse($settings->hasActiveAccess());
        $this->assertSame('inactive', $settings->accessStatus()['status']);
    }

    public function test_plan_change_pushes_plan_and_languages_to_connected_site(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $settings = EuroCrossSettings::factory()->create([
            'user_id' => $user->id,
            'plan' => 'starter',
            'subscription_status' => 'active',
            'subscription_id' => 'SUB-1',
        ]);

        DB::table('eurocross_connected_sites')->insert([
            'shop_id' => $settings->id,
            'site_url' => 'https://shop.example.test',
            'site_secret' => encrypt('site-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake(['shop.example.test/*' => Http::response(['success' => true])]);

        (new ProcessGumroadWebhook($this->payload([
            'event' => 'recurring_charge',
            'product_id' => '03Ex_UcTD52UiV3lhwHZhA==',
            'product_permalink' => 'eurocross-growth',
        ]), 'evt-push', 'recurring_charge'))
            ->handle(app(GumroadProvider::class), app(ConnectedSiteService::class));

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'https://shop.example.test/wp-json/eurocross/v1/plan-sync')
                && $request->hasHeader('X-EuroCross-Secret', 'site-secret')
                && data_get($request->data(), 'plan') === 'growth'
                && data_get($request->data(), 'limits.languages') === 5
                && count(data_get($request->data(), 'allowed_languages', [])) === 5;
        });
    }

    public function test_unmatched_email_is_saved_for_manual_review(): void
    {
        Http::fake();

        (new ProcessGumroadWebhook($this->payload(['email' => 'unknown@example.com']), 'evt-manual', 'sale'))
            ->handle(app(GumroadProvider::class));

        $this->assertDatabaseHas('eurocross_manual_payments', [
            'event_id' => 'evt-manual',
            'email' => 'unknown@example.com',
            'plan' => 'starter',
            'status' => 'pending',
        ]);
    }

    public function test_webhook_registration_includes_sale_event(): void
    {
        config()->set('payment.gumroad.access_token', 'test-gumroad-token');

        Http::fake([
            'api.gumroad.com/*' => Http::response(['resource_subscriptions' => []]),
        ]);

        $this->artisan('gumroad:register-webhooks')->assertSuccessful();

        Http::assertSent(function ($request): bool {
            return $request->method() === 'PUT'
                && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
                && data_get($request->data(), 'resource_name') === 'sale'
                && str_contains((string) data_get($request->data(), 'post_url'), '/eurocross/webhook/gumroad');
        });
    }

    public function test_webhook_registration_is_idempotent(): void
    {
        config()->set('payment.gumroad.access_token', 'test-gumroad-token');

        Http::fake([
            'api.gumroad.com/*' => Http::response([
                'resource_subscriptions' => [[
                    'resource_name' => 'sale',
                    'post_url' => route('eurocross.webhook.gumroad'),
                ]],
            ]),
        ]);

        $this->artisan('gumroad:register-webhooks')
            ->expectsOutput('Already registered: sale')
            ->assertSuccessful();

        Http::assertNotSent(fn ($request): bool => $request->method() === 'PUT');
        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && data_get($request->data(), 'resource_name') === 'sale';
        });
    }

    public function test_pricing_page_renders_three_subscribe_links_and_script_once(): void
    {
        $response = $this->get('/pricing')->assertOk()->assertSee('Subscribe to Starter')->assertSee('Subscribe to Growth')->assertSee('Subscribe to Scale');

        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'gumroad.com/js/gumroad.js'));
        $this->assertStringContainsString(route('billing.checkout', 'starter'), $html);
        $this->assertStringContainsString(route('billing.checkout', 'growth'), $html);
        $this->assertStringContainsString(route('billing.checkout', 'scale'), $html);
        $this->assertStringNotContainsString('https://gumroad.test/l/starter', $html);
        $this->assertStringNotContainsString('https://gumroad.test/l/growth', $html);
        $this->assertStringNotContainsString('https://gumroad.test/l/scale', $html);
    }

    public function test_checkout_routes_require_authentication(): void
    {
        $this->get(route('checkout.starter'))->assertRedirect(route('login'));
        $this->get(route('checkout.growth'))->assertRedirect(route('login'));
        $this->get(route('checkout.scale'))->assertRedirect(route('login'));
        $this->get(route('billing.checkout', 'starter'))->assertRedirect(route('login'));
    }

    public function test_authenticated_checkout_redirects_to_configured_gumroad_url(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);

        $response = $this->actingAs($user)->get(route('billing.checkout', 'starter'));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://gumroad.test/l/starter', $location);
        $this->assertStringContainsString('email=buyer%40example.com', $location);
    }
}
