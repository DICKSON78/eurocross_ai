<?php

namespace App\Http\Controllers\EuroCross\Api;

use App\Http\Controllers\Controller;
use App\Models\EuroCrossSettings;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TranslationController extends Controller
{
    public function __construct(private TranslationService $translationService) {}

    public function languages(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->translationService->languages(),
        ]);
    }

    public function translate(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:10000'],
            'source_lang' => ['sometimes', 'string', 'size:2'],
            'target_lang' => ['required', 'string', Rule::in($this->languageCodes())],
        ]);

        $text = (string) $validated['text'];
        $targetLang = (string) $validated['target_lang'];
        $sourceLang = (string) ($validated['source_lang'] ?? 'en');
        $wordCount = $this->countWords($text);

        $quotaError = $this->ensureWithinQuota($settings, $wordCount);
        if ($quotaError !== null) {
            return $quotaError;
        }

        $result = $this->translationService->for($settings)->translate($text, $targetLang, $sourceLang);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Translation failed.',
            ], 502);
        }

        $settings->trackUsage(
            (int) ($result['tokens'] ?? 0),
            1,
            $wordCount,
            (float) ($result['cost'] ?? 0),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'original' => $text,
                'translated' => (string) ($result['translated'] ?? ''),
                'target_language' => $targetLang,
                'source_language' => $sourceLang,
                'provider' => $result['provider'] ?? 'unknown',
                'tokens' => (int) ($result['tokens'] ?? 0),
                'cost' => (float) ($result['cost'] ?? 0),
                'demo' => (bool) ($result['demo'] ?? false),
            ],
        ]);
    }

    public function translateBatch(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $validated = $request->validate([
            'texts' => ['required', 'array', 'min:1', 'max:100'],
            'texts.*' => ['required', 'string', 'max:10000'],
            'source_lang' => ['sometimes', 'string', 'size:2'],
            'target_lang' => ['required', 'string', Rule::in($this->languageCodes())],
        ]);

        /** @var array<int, string> $texts */
        $texts = array_values($validated['texts']);
        $targetLang = (string) $validated['target_lang'];
        $sourceLang = (string) ($validated['source_lang'] ?? 'en');
        $wordCount = array_sum(array_map(fn (string $text): int => $this->countWords($text), $texts));

        $quotaError = $this->ensureWithinQuota($settings, $wordCount);
        if ($quotaError !== null) {
            return $quotaError;
        }

        $result = $this->translationService->for($settings)->batchTranslate($texts, $targetLang, $sourceLang);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => implode(' | ', $result['errors'] ?? ['Batch translation failed.']),
            ], 502);
        }

        $settings->trackUsage(
            (int) ($result['total_tokens'] ?? 0),
            count($texts),
            $wordCount,
            (float) ($result['total_cost'] ?? 0),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'results' => $result['results'] ?? [],
                'target_language' => $targetLang,
                'source_language' => $sourceLang,
                'total_texts' => (int) ($result['total_texts'] ?? count($texts)),
                'total_tokens' => (int) ($result['total_tokens'] ?? 0),
                'total_cost' => (float) ($result['total_cost'] ?? 0),
                'provider' => $result['provider'] ?? 'unknown',
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function languageCodes(): array
    {
        return array_column($this->translationService->languages(), 'code');
    }

    private function countWords(string $text): int
    {
        $words = str_word_count($text);

        return max(1, $words > 0 ? $words : (int) ceil(mb_strlen($text) / 4));
    }

    private function ensureWithinQuota(EuroCrossSettings $settings, int $requestedWords): ?JsonResponse
    {
        $limit = $settings->planWordLimit();

        if ($limit <= 0) {
            return null;
        }

        $used = (int) $settings->translationUsage()
            ->whereBetween('usage_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('words_translated');

        if ($used + $requestedWords > $limit) {
            return response()->json([
                'success' => false,
                'error' => 'Monthly word allowance exceeded. Upgrade your plan to continue translating.',
                'data' => [
                    'used' => $used,
                    'limit' => $limit,
                    'requested' => $requestedWords,
                ],
            ], 429);
        }

        return null;
    }

    private function shop(Request $request): EuroCrossSettings
    {
        $settings = $request->attributes->get('eurocross_settings');

        abort_unless($settings instanceof EuroCrossSettings, 401, 'Authenticated shop context is missing.');

        return $settings;
    }
}
