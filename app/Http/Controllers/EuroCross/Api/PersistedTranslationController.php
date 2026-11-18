<?php

namespace App\Http\Controllers\EuroCross\Api;

use App\Http\Controllers\Controller;
use App\Models\EuroCrossSettings;
use App\Models\EuroCrossTranslation;
use App\Services\PersistedTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $record = $this->translations->translate($shop, [
            'text' => (string) $validated['text'],
            'source_type' => (string) ($validated['source_type'] ?? 'manual'),
            'source_id' => (string) ($validated['source_id'] ?? '0'),
            'field' => (string) ($validated['field'] ?? 'content'),
            'source_locale' => (string) ($validated['source_lang'] ?? 'en'),
            'target_locale' => (string) $validated['target_lang'],
        ]);

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

    private function shop(Request $request): EuroCrossSettings
    {
        $settings = $request->attributes->get('eurocross_settings');

        abort_unless($settings instanceof EuroCrossSettings, 401, 'Authenticated shop context is missing.');

        return $settings;
    }
}
