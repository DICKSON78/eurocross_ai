<?php

namespace Tests\Feature;

use App\Models\EuroCrossWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_lemon_squeezy_webhook_records_event_metadata_and_marks_processed(): void
    {
        config()->set('payment.lemon_squeezy.webhook_secret', 'ls-secret');
        $payload = (string) json_encode([
            'meta' => ['event_id' => 'ls_evt_1'],
            'data' => [
                'type' => 'unknown',
                'attributes' => [],
            ],
        ]);
        $signature = hash_hmac('sha256', $payload, 'ls-secret');

        $this->call(
            'POST',
            '/eurocross/webhook/lemon-squeezy',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $signature,
            ],
            $payload,
        )->assertOk()->assertJson(['success' => true]);

        $record = EuroCrossWebhook::query()->where('provider', 'lemon_squeezy')->firstOrFail();
        $this->assertSame('ls_evt_1', $record->event_id);
        $this->assertSame('unknown', $record->event);
        $this->assertSame($signature, $record->signature);
        $this->assertTrue($record->processed);
    }

    public function test_lemon_squeezy_duplicate_is_detected_by_event_id(): void
    {
        config()->set('payment.lemon_squeezy.webhook_secret', 'ls-secret');
        $payload = (string) json_encode([
            'id' => 'resource-1',
            'meta' => ['event_id' => 'ls_evt_2'],
            'data' => [
                'type' => 'unknown',
                'attributes' => [],
            ],
        ]);
        $signature = hash_hmac('sha256', $payload, 'ls-secret');

        $request = fn () => $this->call(
            'POST',
            '/eurocross/webhook/lemon-squeezy',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $signature,
            ],
            $payload,
        );

        $request()->assertOk()->assertJson(['success' => true]);
        $request()->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertDatabaseCount('eurocross_webhooks', 1);
        $this->assertDatabaseHas('eurocross_webhooks', [
            'provider' => 'lemon_squeezy',
            'event_id' => 'ls_evt_2',
        ]);
    }

    public function test_stripe_webhook_records_event_metadata_and_marks_processed(): void
    {
        config()->set('payment.stripe.webhook_secret', 'whsec_test');
        $timestamp = now()->getTimestamp();
        $payload = (string) json_encode([
            'id' => 'evt_stripe_1',
            'type' => 'unknown',
            'data' => ['object' => []],
        ]);
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $this->call(
            'POST',
            '/eurocross/webhook/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload,
        )->assertOk()->assertJson(['success' => true]);

        $record = EuroCrossWebhook::query()->where('provider', 'stripe')->firstOrFail();
        $this->assertSame('evt_stripe_1', $record->event_id);
        $this->assertSame('unknown', $record->event);
        $this->assertSame("t={$timestamp},v1={$signature}", $record->signature);
        $this->assertTrue($record->processed);
    }
}
