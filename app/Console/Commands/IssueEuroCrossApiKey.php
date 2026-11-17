<?php

namespace App\Console\Commands;

use App\Models\EuroCrossSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:issue-euro-cross-api-key {--shop= : Shop ID. Defaults to the first shop.} {--name=default : Human readable key name.}')]
#[Description('Issue a EuroCross AI API key. The raw key is shown once; only its hash is stored for lookup.')]
class IssueEuroCrossApiKey extends Command
{
    public function handle(): int
    {
        $shopId = $this->option('shop');

        $shop = $shopId
            ? EuroCrossSettings::findOrFail($shopId)
            : EuroCrossSettings::firstOrCreate(
                ['locale' => 'en', 'plan' => 'starter'],
                [
                    'trial' => true,
                    'trial_started_at' => now(),
                    'trial_expires' => now()->addDays((int) config('payment.trial.days', 14)),
                    'subscription_status' => 'trialing',
                ],
            );

        $token = 'ecx_live_'.Str::random(48);

        $apiKey = $shop->apiKeys()->create([
            'provider' => 'eurocross',
            'key_name' => (string) $this->option('name'),
            'encrypted_key' => $token,
            'key_hash' => hash('sha256', $token),
            'active' => true,
        ]);

        $this->info('API key created successfully.');
        $this->line('Key ID: '.$apiKey->id);
        $this->newLine();
        $this->warn('Copy this key now. It will not be shown again:');
        $this->line($token);
        $this->newLine();
        $this->line('Send it as the X-API-Key header to /eurocross/* endpoints.');

        return self::SUCCESS;
    }
}
