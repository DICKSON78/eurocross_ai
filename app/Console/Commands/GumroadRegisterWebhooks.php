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
        $baseUrl = rtrim((string) config('app.url'), '/');

        if ($token === '') {
            $this->error('GUMROAD_ACCESS_TOKEN is not set.');

            return self::FAILURE;
        }

        $postUrl = $baseUrl.'/eurocross/webhook/gumroad';

        $resources = [
            'sale',
            'cancellation',
            'subscription_ended',
            'subscription_restarted',
            'subscription_updated',
            'refund',
            'dispute',
            'dispute_won',
        ];

        foreach ($resources as $resource) {
            $registered = false;

            try {
                $list = Http::connectTimeout(5)->timeout(20)->get(
                    'https://api.gumroad.com/v2/resource_subscriptions',
                    [
                        'access_token' => $token,
                        'resource_name' => $resource,
                    ],
                );

                foreach ((array) $list->json('resource_subscriptions', []) as $subscription) {
                    if (($subscription['post_url'] ?? '') === $postUrl) {
                        $registered = true;

                        break;
                    }
                }
            } catch (\Throwable $e) {
                $this->warn("Could not list subscriptions for {$resource}: ".$e->getMessage());
            }

            if ($registered) {
                $this->info("Already registered: {$resource}");

                continue;
            }

            try {
                $resp = Http::connectTimeout(5)->timeout(20)->asForm()->put('https://api.gumroad.com/v2/resource_subscriptions', [
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
