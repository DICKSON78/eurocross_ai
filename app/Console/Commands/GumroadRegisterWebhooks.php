<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GumroadRegisterWebhooks extends Command
{
    protected $signature = 'gumroad:register-webhooks';

    protected $description = 'Register Gumroad resource subscriptions (idempotent)';

    public function handle(): int
    {
        $token = (string) config('payment.gumroad.access_token');
        $secret = (string) config('payment.gumroad.webhook_secret');
        $baseUrl = rtrim((string) config('app.url'), '/');

        if ($token === '') {
            $this->error('GUMROAD_ACCESS_TOKEN is not set.');

            return self::FAILURE;
        }

        $postUrl = $baseUrl.'/eurocross/webhook/gumroad'.($secret !== '' ? '?secret='.urlencode($secret) : '');

        $resources = [
            'cancellation',
            'subscription_ended',
            'subscription_restarted',
            'subscription_updated',
            'refund',
            'dispute',
            'dispute_won',
        ];

        // List existing subscriptions
        $existing = [];
        try {
            $list = Http::connectTimeout(5)->timeout(20)->get('https://api.gumroad.com/v2/resource_subscriptions', [
                'access_token' => $token,
            ]);
            if ($list->successful()) {
                foreach ($list->json('resource_subscriptions', []) as $sub) {
                    $existing[] = ($sub['resource_name'] ?? '').'|'.($sub['post_url'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            $this->warn('Could not list existing subscriptions: '.$e->getMessage());
        }

        foreach ($resources as $resource) {
            $key = $resource.'|'.$postUrl;
            if (in_array($key, $existing, true)) {
                $this->info("Already registered: {$resource}");

                continue;
            }

            try {
                $resp = Http::connectTimeout(5)->timeout(20)->asForm()->post('https://api.gumroad.com/v2/resource_subscriptions', [
                    'access_token' => $token,
                    'resource_name' => $resource,
                    'post_url' => $postUrl,
                ]);

                if ($resp->successful()) {
                    $this->info("Registered: {$resource}");
                } else {
                    $this->error("Failed to register {$resource}: ".$resp->body());
                }
            } catch (\Throwable $e) {
                $this->error("Error registering {$resource}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
