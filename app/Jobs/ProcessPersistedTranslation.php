<?php

namespace App\Jobs;

use App\Models\EuroCrossTranslation;
use App\Models\EuroCrossTranslationJob;
use App\Services\TranslationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProcessPersistedTranslation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [1, 5, 10];

    public int $uniqueFor = 3600;

    public function __construct(
        public int $translationId,
        public int $jobId,
    ) {}

    public function handle(TranslationService $translationService): void
    {
        $translation = EuroCrossTranslation::query()->findOrFail($this->translationId);
        $job = EuroCrossTranslationJob::query()->findOrFail($this->jobId);

        if ($translation->manually_edited || $translation->status === 'completed') {
            $job->markCompleted();

            return;
        }

        DB::transaction(function () use ($translation, $job): void {
            $claimed = EuroCrossTranslation::query()
                ->whereKey($translation->id)
                ->whereIn('status', ['pending', 'failed'])
                ->update(['status' => 'processing']);

            if ($claimed !== 1) {
                throw new RuntimeException('Translation is no longer pending.');
            }

            $job->markProcessing();
        });

        $result = $translationService
            ->for($translation->shop)
            ->translate($translation->source_text, $translation->target_locale, $translation->source_locale);

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException((string) ($result['error'] ?? 'Translation provider failed.'));
        }

        $translation->markCompleted($result);
        $translation->shop->trackUsage(
            (int) ($result['tokens'] ?? 0),
            1,
            (int) $translation->words,
            (float) ($result['cost'] ?? 0),
        );
        $job->markCompleted();
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception?->getMessage() ?: 'Translation job failed.';

        EuroCrossTranslation::query()
            ->find($this->translationId)
            ?->markFailed($message);

        EuroCrossTranslationJob::query()
            ->find($this->jobId)
            ?->markFailed($message);
    }

    public function uniqueId(): string
    {
        return 'translation-'.$this->translationId;
    }
}
