<?php

namespace App\Services;

use App\Jobs\ProcessPersistedTranslation;
use App\Models\EuroCrossSettings;
use App\Models\EuroCrossTranslation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class PersistedTranslationService
{
    public function __construct(private TranslationService $translationService) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public function translate(
        EuroCrossSettings $shop,
        array $item,
        ?string $batchId = null,
        bool $force = false,
    ): EuroCrossTranslation {
        $sourceText = (string) ($item['text'] ?? '');
        $sourceType = (string) ($item['source_type'] ?? 'manual');
        $sourceId = (string) ($item['source_id'] ?? '0');
        $field = (string) ($item['field'] ?? 'content');
        $sourceLocale = (string) ($item['source_locale'] ?? 'en');
        $targetLocale = (string) ($item['target_locale'] ?? '');
        $sourceHash = $this->hash($sourceText, $sourceLocale, $targetLocale);

        $translation = EuroCrossTranslation::query()->firstOrCreate(
            [
                'shop_id' => $shop->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'field' => $field,
                'source_locale' => $sourceLocale,
                'target_locale' => $targetLocale,
            ],
            [
                'source_hash' => $sourceHash,
                'source_text' => $sourceText,
                'words' => $this->countWords($sourceText),
                'status' => 'pending',
                'batch_id' => $batchId,
            ]
        );

        if (! $force && $translation->manually_edited) {
            return $translation;
        }

        $unchanged = $translation->source_hash === $sourceHash
            && $translation->source_text === $sourceText
            && filled($translation->translated_text);

        if (! $force && $translation->status === 'completed' && $unchanged) {
            return $translation;
        }

        if ($translation->status === 'processing') {
            return $translation;
        }

        $translation->forceFill([
            'source_hash' => $sourceHash,
            'source_text' => $sourceText,
            'target_locale' => $targetLocale,
            'words' => $this->countWords($sourceText),
            'status' => 'pending',
            'last_error' => null,
            'batch_id' => $batchId ?? $translation->batch_id,
        ]);

        if ($force) {
            $translation->translated_text = null;
        }

        $translation->save();

        $this->queue($translation, $batchId ?? $translation->batch_id);

        return $translation->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, EuroCrossTranslation>
     */
    public function bulk(EuroCrossSettings $shop, array $items, ?string $sourceLocale = null): Collection
    {
        $batchId = (string) Str::uuid();

        return collect($items)->map(function (array $item) use ($shop, $batchId, $sourceLocale): EuroCrossTranslation {
            $item['source_locale'] ??= $sourceLocale ?? 'en';

            return $this->translate($shop, $item, $batchId);
        });
    }

    public function manualEdit(
        EuroCrossSettings $shop,
        EuroCrossTranslation $translation,
        string $translatedText,
    ): EuroCrossTranslation {
        $this->assertOwned($shop, $translation);

        $translation->forceFill([
            'translated_text' => $translatedText,
            'manually_edited' => true,
            'status' => 'completed',
            'provider' => 'manual',
            'last_error' => null,
        ])->save();

        $translation->jobs()
            ->whereIn('status', ['queued', 'processing'])
            ->update(['status' => 'completed', 'finished_at' => now()]);

        return $translation->refresh();
    }

    public function retry(EuroCrossSettings $shop, EuroCrossTranslation $translation): EuroCrossTranslation
    {
        $this->assertOwned($shop, $translation);

        if (! in_array($translation->status, ['failed', 'completed'], true)) {
            throw new RuntimeException('Only failed or completed translations can be retried.');
        }

        $translation->forceFill([
            'status' => 'pending',
            'last_error' => null,
        ])->save();

        $this->queue($translation, $translation->batch_id);

        return $translation->refresh();
    }

    /**
     * @return array<int, string>
     */
    public function allowedLanguageCodes(EuroCrossSettings $shop): array
    {
        return array_column($this->translationService->allowedLanguages($shop), 'code');
    }

    private function assertOwned(EuroCrossSettings $shop, EuroCrossTranslation $translation): void
    {
        abort_unless($translation->shop_id === $shop->id, 404);
    }

    private function queue(EuroCrossTranslation $translation, ?string $batchId): void
    {
        $hasActiveJob = $translation->jobs()
            ->whereIn('status', ['queued', 'processing'])
            ->exists();

        if ($hasActiveJob) {
            return;
        }

        $job = $translation->jobs()->create([
            'shop_id' => $translation->shop_id,
            'batch_id' => $batchId,
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        ProcessPersistedTranslation::dispatch($translation->id, $job->id);
    }

    private function hash(string $text, string $sourceLocale, string $targetLocale): string
    {
        return hash('sha256', implode("\0", [$sourceLocale, $targetLocale, $text]));
    }

    private function countWords(string $text): int
    {
        $words = str_word_count($text);

        return max(1, $words > 0 ? $words : (int) ceil(mb_strlen($text) / 4));
    }
}
