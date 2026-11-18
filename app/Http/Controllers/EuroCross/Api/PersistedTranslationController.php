<?php

namespace App\Http\Controllers\EuroCross\Api;

use App\Exceptions\QuotaExceededException;
use App\Http\Controllers\Controller;
use App\Models\EuroCrossQuotaReservation;
use App\Models\EuroCrossSettings;
use App\Models\EuroCrossTranslation;
use App\Services\PersistedTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use RuntimeException;

class PersistedTranslationController extends Controller
{
    private const STATUSES = ['pending', 'processing', 'completed', 'failed'];

    public function __construct(private PersistedTranslationService $translations) {}

    public function index(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'target_lang' => ['sometimes', 'string', 'size:2'],
            'source_type' => ['sometimes', 'string', 'max:64'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = EuroCrossTranslation::query()->forShop($shop->id);

        if (isset($validated['status'])) {
            $query->withStatus((string) $validated['status']);
        }

        if (isset($validated['target_lang'])) {
            $query->where('target_locale', (string) $validated['target_lang']);
        }

        if (isset($validated['source_type'])) {
            $query->where('source_type', (string) $validated['source_type']);
        }

        $paginator = $query->latest()->paginate((int) ($validated['per_page'] ?? 20));
        $data = $paginator->getCollection()
            ->map(fn (EuroCrossTranslation $translation): array => $this->payload($translation))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ]);
        $days = (int) ($validated['days'] ?? 30);

        $statusRows = EuroCrossTranslation::query()
            ->forShop($shop->id)
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(words), 0) as words, COALESCE(SUM(retry_count), 0) as retries')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statuses = [];
        foreach (self::STATUSES as $status) {
            $row = $statusRows->get($status);
            $statuses[$status] = [
                'total' => (int) ($row->total ?? 0),
                'words' => (int) ($row->words ?? 0),
                'retries' => (int) ($row->retries ?? 0),
            ];
        }

        $totals = EuroCrossTranslation::query()
            ->forShop($shop->id)
            ->selectRaw(
                'COUNT(*) as translations, COALESCE(SUM(words), 0) as words, '
                .'COALESCE(SUM(tokens), 0) as tokens, COALESCE(SUM(cost), 0) as cost, '
                .'COALESCE(SUM(retry_count), 0) as retries, '
                .'COALESCE(SUM(CASE WHEN manually_edited = 1 THEN 1 ELSE 0 END), 0) as manual_edits, '
                .'COUNT(DISTINCT batch_id) as batches'
            )
            ->firstOrFail();

        $recent = EuroCrossTranslation::query()
            ->forShop($shop->id)
            ->latest()
            ->limit(10)
            ->get(['id', 'source_type', 'source_id', 'field', 'target_locale', 'status', 'words', 'retry_count', 'manually_edited', 'created_at'])
            ->map(static fn (EuroCrossTranslation $translation): array => [
                'id' => $translation->id,
                'source_type' => $translation->source_type,
                'source_id' => $translation->source_id,
                'field' => $translation->field,
                'target_language' => $translation->target_locale,
                'status' => $translation->status,
                'words' => $translation->words,
                'retries' => $translation->retry_count,
                'manually_edited' => $translation->manually_edited,
                'created_at' => $translation->created_at?->toIso8601String(),
            ])
            ->all();

        $byLanguage = EuroCrossTranslation::query()
            ->forShop($shop->id)
            ->selectRaw('target_locale, COUNT(*) as total, COALESCE(SUM(words), 0) as words')
            ->groupBy('target_locale')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(static fn (EuroCrossTranslation $translation): array => [
                'language' => $translation->target_locale,
                'total' => (int) $translation->total,
                'words' => (int) $translation->words,
            ])
            ->all();

        $since = now()->subDays($days - 1)->startOfDay();
        $recentTotals = EuroCrossTranslation::query()
            ->forShop($shop->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) as translations, COALESCE(SUM(words), 0) as words')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'statuses' => $statuses,
                'totals' => [
                    'translations' => (int) $totals->translations,
                    'words' => (int) $totals->words,
                    'tokens' => (int) $totals->tokens,
                    'cost' => (float) $totals->cost,
                    'retries' => (int) $totals->retries,
                    'manual_edits' => (int) $totals->manual_edits,
                    'batches' => (int) $totals->batches,
                    'recent_translations' => (int) $recentTotals->translations,
                    'recent_words' => (int) $recentTotals->words,
                ],
                'quota' => $this->quota($shop),
                'by_language' => $byLanguage,
                'recent' => $recent,
                'window_days' => $days,
            ],
        ]);
    }

    public function show(Request $request, int $translation): JsonResponse
    {
        $shop = $this->shop($request);
        $record = $this->findForShop($shop, $translation);

        return response()->json([
            'success' => true,
            'data' => $this->payload($record),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $validated = $request->validate($this->itemRules($shop, 'text'));

        try {
            $record = $this->translations->translate($shop, [
                'text' => (string) $validated['text'],
                'source_type' => (string) ($validated['source_type'] ?? 'manual'),
                'source_id' => (string) ($validated['source_id'] ?? '0'),
                'field' => (string) ($validated['field'] ?? 'content'),
                'source_locale' => (string) ($validated['source_lang'] ?? 'en'),
                'target_locale' => (string) $validated['target_lang'],
            ]);
        } catch (QuotaExceededException $exception) {
            return $this->quotaError($exception);
        }

        return response()->json([
            'success' => true,
            'data' => $this->payload($record),
        ], 201);
    }

    public function bulk(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $validated = $request->validate([
            'source_lang' => ['sometimes', 'string', 'size:2'],
            'target_lang' => ['required', 'string', Rule::in($this->translations->allowedLanguageCodes($shop))],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.text' => ['required', 'string', 'max:10000'],
            'items.*.source_type' => ['sometimes', 'string', 'max:64'],
            'items.*.source_id' => ['sometimes', 'string', 'max:64'],
            'items.*.field' => ['sometimes', 'string', 'max:64'],
        ]);

        /** @var array<int, array<string, mixed>> $items */
        $items = array_values((array) $validated['items']);
        $sourceLocale = (string) ($validated['source_lang'] ?? 'en');
        $targetLocale = (string) $validated['target_lang'];

        try {
            $records = $this->translations->bulk(
                $shop,
                array_map(static function (array $item) use ($sourceLocale, $targetLocale): array {
                    return array_merge($item, [
                        'source_locale' => $sourceLocale,
                        'target_locale' => $targetLocale,
                    ]);
                }, $items),
                $sourceLocale,
            );
        } catch (QuotaExceededException $exception) {
            return $this->quotaError($exception);
        }

        return response()->json([
            'success' => true,
            'data' => $records->map(fn (EuroCrossTranslation $record): array => $this->payload($record))->all(),
            'meta' => [
                'total' => $records->count(),
                'batch_id' => $records->first()?->batch_id,
            ],
        ], 201);
    }

    public function update(Request $request, int $translation): JsonResponse
    {
        $shop = $this->shop($request);
        $validated = $request->validate([
            'translated_text' => ['required', 'string', 'max:10000'],
        ]);

        $record = $this->findForShop($shop, $translation);
        $record = $this->translations->manualEdit($shop, $record, (string) $validated['translated_text']);

        return response()->json([
            'success' => true,
            'data' => $this->payload($record),
        ]);
    }

    public function retry(Request $request, int $translation): JsonResponse
    {
        $shop = $this->shop($request);
        $record = $this->findForShop($shop, $translation);

        try {
            $record = $this->translations->retry($shop, $record);
        } catch (QuotaExceededException $exception) {
            return $this->quotaError($exception);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->payload($record),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function itemRules(EuroCrossSettings $shop, string $textKey): array
    {
        return [
            $textKey => ['required', 'string', 'max:10000'],
            'source_type' => ['sometimes', 'string', 'max:64'],
            'source_id' => ['sometimes', 'string', 'max:64'],
            'field' => ['sometimes', 'string', 'max:64'],
            'source_lang' => ['sometimes', 'string', 'size:2'],
            'target_lang' => ['required', 'string', Rule::in($this->translations->allowedLanguageCodes($shop))],
        ];
    }

    private function findForShop(EuroCrossSettings $shop, int $translation): EuroCrossTranslation
    {
        return EuroCrossTranslation::query()
            ->forShop($shop->id)
            ->findOrFail($translation);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EuroCrossTranslation $translation): array
    {
        return [
            'id' => $translation->id,
            'source_type' => $translation->source_type,
            'source_id' => $translation->source_id,
            'field' => $translation->field,
            'source_text' => $translation->source_text,
            'translated_text' => $translation->translated_text,
            'source_language' => $translation->source_locale,
            'target_language' => $translation->target_locale,
            'status' => $translation->status,
            'manually_edited' => $translation->manually_edited,
            'retry_count' => $translation->retry_count,
            'provider' => $translation->provider,
            'error' => $translation->last_error,
            'words' => $translation->words,
            'tokens' => $translation->tokens,
            'cost' => (float) $translation->cost,
            'batch_id' => $translation->batch_id,
            'job_status' => $translation->jobs()->latest()->value('status'),
        ];
    }

    private function quotaError(QuotaExceededException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $exception->getMessage(),
            'data' => $exception->context(),
        ], 429);
    }

    /**
     * @return array{limit: int, used: int, reserved: int, remaining: int}
     */
    private function quota(EuroCrossSettings $shop): array
    {
        $limit = $shop->planWordLimit();
        $start = now()->startOfMonth();

        if ($shop->words_reset_at instanceof Carbon && $shop->words_reset_at->greaterThan($start)) {
            $start = $shop->words_reset_at->copy()->startOfDay();
        }

        $used = (int) $shop->translationUsage()
            ->whereBetween('usage_date', [$start->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('words_translated');

        $reserved = (int) EuroCrossQuotaReservation::query()
            ->forShop($shop->id)
            ->active()
            ->where('created_at', '>=', $start)
            ->sum('words');

        return [
            'limit' => $limit,
            'used' => $used,
            'reserved' => $reserved,
            'remaining' => $limit > 0 ? max(0, $limit - $used - $reserved) : 0,
        ];
    }

    private function shop(Request $request): EuroCrossSettings
    {
        $settings = $request->attributes->get('eurocross_settings');

        abort_unless($settings instanceof EuroCrossSettings, 401, 'Authenticated shop context is missing.');

        return $settings;
    }
}
