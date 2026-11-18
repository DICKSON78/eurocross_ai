<?php

namespace App\Services;

use App\Models\EuroCrossSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConnectedSiteService
{
    public function __construct(private TranslationService $translationService) {}

    public function attach(int $shopId, string $siteUrl, string $secret): void
    {
        $exists = DB::table('eurocross_connected_sites')
            ->where('shop_id', $shopId)
            ->where('site_url', $siteUrl)
            ->exists();

        if ($exists) {
            DB::table('eurocross_connected_sites')
                ->where('shop_id', $shopId)
                ->where('site_url', $siteUrl)
                ->update([
                    'site_secret' => encrypt($secret),
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('eurocross_connected_sites')->insert([
            'shop_id' => $shopId,
            'site_url' => $siteUrl,
            'site_secret' => encrypt($secret),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function sync(EuroCrossSettings $settings): void
    {
        $sites = DB::table('eurocross_connected_sites')
            ->where('shop_id', $settings->id)
            ->get(['site_url', 'site_secret']);

        if ($sites->isEmpty()) {
            return;
        }

        $plan = $settings->plan;
        $payload = [
            'plan' => $plan,
            'plan_name' => $settings->getPlanDisplayName(),
            'status' => $settings->accessStatus()['status'],
            'access_allowed' => $settings->hasActiveAccess(),
            'limits' => [
                'languages' => $settings->planLanguageLimit(),
                'words' => $settings->planWordLimit(),
                'rate_limit' => $settings->rateLimit(),
                'vat' => (string) config("payment.plans.{$plan}.features.vat", 'basic'),
                'chatbot' => (bool) config("payment.plans.{$plan}.features.chatbot", false),
            ],
            'allowed_languages' => $this->translationService->allowedLanguages($settings),
        ];

        foreach ($sites as $site) {
            $siteUrl = (string) $site->site_url;

            try {
                $secret = decrypt((string) $site->site_secret);

                $response = Http::connectTimeout(3)->timeout(10)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'X-EuroCross-Secret' => $secret,
                    ])
                    ->post(rtrim($siteUrl, '/').'/wp-json/eurocross/v1/plan-sync', $payload);

                if (! $response->successful()) {
                    Log::channel('api')->warning('EuroCross connected site sync rejected', [
                        'site_url' => $siteUrl,
                        'shop_id' => $settings->id,
                        'status' => $response->status(),
                    ]);
                }
            } catch (Throwable $exception) {
                Log::channel('api')->warning('EuroCross connected site sync failed', [
                    'site_url' => $siteUrl,
                    'shop_id' => $settings->id,
                ]);
            }
        }
    }
}
