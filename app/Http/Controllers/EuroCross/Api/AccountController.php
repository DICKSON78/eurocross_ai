<?php

namespace App\Http\Controllers\EuroCross\Api;

use App\Http\Controllers\Controller;
use App\Models\EuroCrossSettings;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(private TranslationService $translationService) {}

    public function summary(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $usage = $this->usageTotals($settings, now()->startOfMonth(), now()->endOfMonth());
        $wordLimit = $settings->planWordLimit();
        $remainingWords = max(0, $wordLimit - $usage['words']);
        $usagePercentage = $wordLimit > 0
            ? round(min(100, ($usage['words'] / $wordLimit) * 100), 2)
            : 0.0;

        $activeProviders = $settings->apiKeys()
            ->where('active', true)
            ->whereIn('provider', ['openai', 'deepl'])
            ->pluck('provider')
            ->all();

        $hasOpenAi = in_array('openai', $activeProviders, true) || filled(config('services.openai.key'));
        $hasDeepL = in_array('deepl', $activeProviders, true) || filled(config('services.deepl.key'));
        $isDemo = $this->translationService->for($settings)->isDemoMode();

        return response()->json([
            'success' => true,
            'data' => [
                'account' => [
                    'plan' => $settings->plan,
                    'plan_name' => $settings->getPlanDisplayName(),
                    'status' => $settings->accessStatus()['status'],
                    'access_reason' => $settings->accessStatus()['reason'],
                    'trial' => [
                        'active' => $settings->isTrial(),
                        'days_remaining' => $settings->trialRemainingDays(),
                        'expires_at' => $settings->trial_expires?->toIso8601String(),
                        'grace_ends_at' => $settings->gracePeriodEndsAt()?->toIso8601String(),
                    ],
                ],
                'usage' => [
                    'tokens_this_month' => $usage['tokens'],
                    'strings_this_month' => $usage['strings'],
                    'words_this_month' => $usage['words'],
                    'cost_this_month' => $usage['cost'],
                    'remaining_words' => $remainingWords,
                    'usage_percentage' => $usagePercentage,
                ],
                'providers' => [
                    'ai' => $isDemo ? 'demo' : 'live',
                    'primary' => $hasOpenAi ? 'openai' : ($hasDeepL ? 'deepl' : 'none'),
                    'fallback' => $hasOpenAi && $hasDeepL ? 'deepl' : 'none',
                ],
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $settings = $this->shop($request);

        $usageStats = $settings->translationUsage()
            ->latest('usage_date')
            ->limit(30)
            ->get()
            ->map(fn ($item): array => [
                'date' => $item->usage_date?->toDateString(),
                'tokens' => $item->tokens_used,
                'strings' => $item->strings_translated,
                'words' => $item->words_translated,
                'cost' => (float) $item->ai_cost,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $settings->only(['plan', 'locale', 'trial']),
                'usage_stats' => $usageStats,
                'plan_features' => array_values($settings->getPlanFeatures()),
                'is_trial' => $settings->isTrial(),
                'trial_days_remaining' => $settings->trialRemainingDays(),
            ],
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);
        $days = (int) ($validated['days'] ?? 30);

        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        $usageByDate = $settings->translationUsage()
            ->whereBetween('usage_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($item) => $item->usage_date?->toDateString());

        $series = [];
        $totals = ['tokens' => 0, 'strings' => 0, 'words' => 0, 'cost' => 0.0];

        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addDay()) {
            $date = $cursor->toDateString();
            $usage = $usageByDate->get($date);

            $point = [
                'date' => $date,
                'tokens' => (int) ($usage->tokens_used ?? 0),
                'strings' => (int) ($usage->strings_translated ?? 0),
                'words' => (int) ($usage->words_translated ?? 0),
                'cost' => round((float) ($usage->ai_cost ?? 0), 6),
            ];

            $series[] = $point;
            $totals['tokens'] += $point['tokens'];
            $totals['strings'] += $point['strings'];
            $totals['words'] += $point['words'];
            $totals['cost'] += $point['cost'];
        }

        $totals['cost'] = round($totals['cost'], 6);

        return response()->json([
            'success' => true,
            'data' => [
                'series' => $series,
                'totals' => $totals,
                'plan_limits' => [
                    'words' => $settings->planWordLimit(),
                    'languages' => $settings->planLanguageLimit(),
                    'rate_limit' => $settings->rateLimit(),
                ],
            ],
        ]);
    }

    /**
     * @return array{tokens: int, strings: int, words: int, cost: float}
     */
    private function usageTotals(EuroCrossSettings $settings, $start, $end): array
    {
        $row = $settings->translationUsage()
            ->whereBetween('usage_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COALESCE(SUM(tokens_used), 0) as tokens, COALESCE(SUM(strings_translated), 0) as strings, COALESCE(SUM(words_translated), 0) as words, COALESCE(SUM(ai_cost), 0) as cost')
            ->first();

        return [
            'tokens' => (int) ($row->tokens ?? 0),
            'strings' => (int) ($row->strings ?? 0),
            'words' => (int) ($row->words ?? 0),
            'cost' => round((float) ($row->cost ?? 0), 6),
        ];
    }

    private function shop(Request $request): EuroCrossSettings
    {
        $settings = $request->attributes->get('eurocross_settings');

        abort_unless($settings instanceof EuroCrossSettings, 401, 'Authenticated shop context is missing.');

        return $settings;
    }
}
