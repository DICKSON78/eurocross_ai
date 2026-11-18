<?php

namespace Tests\Feature;

use App\Jobs\ProcessPersistedTranslation;
use App\Models\EuroCrossApiKey;
use App\Models\EuroCrossSettings;
use App\Models\EuroCrossTranslation;
use App\Models\EuroCrossTranslationJob;
use App\Services\PersistedTranslationService;
use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class PersistedTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_persists_translation_and_queues_job(): void
    {
        Queue::fake();
        $this->authenticate('ecx_live_persist_token');

        $this->postJson('/eurocross/translations', [
            'text' => 'Bonjour le monde',
            'source_type' => 'product',
            'source_id' => '101',
            'field' => 'title',
            'source_lang' => 'fr',
            'target_lang' => 'en',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $translation = EuroCrossTranslation::query()->firstOrFail();

        $this->assertSame('Bonjour le monde', $translation->source_text);
        $this->assertSame('en', $translation->target_locale);
        $this->assertDatabaseCount('eurocross_translation_jobs', 1);

        Queue::assertPushed(ProcessPersistedTranslation::class);
    }

    public function test_duplicate_store_reuses_translation_without_second_job(): void
    {
        Queue::fake();
        $shop = $this->authenticate('ecx_live_dedupe_token');

        $service = app(PersistedTranslationService::class);
        $first = $service->translate($shop, [
            'text' => 'Same source text',
            'source_type' => 'product',
            'source_id' => '202',
            'field' => 'title',
            'target_locale' => 'de',
        ]);
        $second = $service->translate($shop, [
            'text' => 'Same source text',
            'source_type' => 'product',
            'source_id' => '202',
            'field' => 'title',
            'target_locale' => 'de',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('eurocross_translations', 1);
        $this->assertDatabaseCount('eurocross_translation_jobs', 1);
    }

    public function test_bulk_groups_translations_into_one_batch(): void
    {
        Queue::fake();
        $this->authenticate('ecx_live_bulk_token');

        $this->postJson('/eurocross/translations/bulk', [
            'source_lang' => 'en',
            'target_lang' => 'fr',
            'items' => [
                ['text' => 'First title', 'field' => 'title', 'source_id' => '1'],
                ['text' => 'First description', 'field' => 'description', 'source_id' => '1'],
                ['text' => 'Second title', 'field' => 'title', 'source_id' => '2'],
            ],
        ])->assertCreated()->assertJsonPath('meta.total', 3);

        $batchId = (string) EuroCrossTranslation::query()->value('batch_id');

        $this->assertNotSame('', $batchId);
        $this->assertSame(3, EuroCrossTranslation::query()->where('batch_id', $batchId)->count());
        $this->assertSame(3, EuroCrossTranslationJob::query()->where('batch_id', $batchId)->count());
    }

    public function test_manual_edit_completes_translation_and_blocks_job_overwrite(): void
    {
        Queue::fake();
        $shop = $this->authenticate('ecx_live_manual_token');
        $translation = EuroCrossTranslation::factory()->for($shop, 'shop')->create([
            'source_text' => 'Original',
            'target_locale' => 'fr',
        ]);
        $job = EuroCrossTranslationJob::factory()->for($translation, 'translation')->create();

        $this->putJson("/eurocross/translations/{$translation->id}", [
            'translated_text' => 'Manually reviewed text',
        ])->assertOk()
            ->assertJsonPath('data.translated_text', 'Manually reviewed text')
            ->assertJsonPath('data.manually_edited', true);

        (new ProcessPersistedTranslation($translation->id, $job->id))
            ->handle(app(TranslationService::class));

        $this->assertSame('Manually reviewed text', $translation->fresh()->translated_text);
        $this->assertSame('completed', $job->fresh()->status);
    }

    public function test_retry_redispatches_failed_translation(): void
    {
        Queue::fake();
        $shop = $this->authenticate('ecx_live_retry_token');
        $translation = EuroCrossTranslation::factory()->for($shop, 'shop')->failed()->create();
        EuroCrossTranslationJob::factory()->for($translation, 'translation')->create(['status' => 'failed']);

        $this->postJson("/eurocross/translations/{$translation->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame(2, EuroCrossTranslationJob::query()->count());
        Queue::assertPushed(ProcessPersistedTranslation::class);
    }

    public function test_cross_shop_translation_returns_not_found(): void
    {
        $this->authenticate('ecx_live_owner_token');
        $otherShop = EuroCrossSettings::factory()->create();
        $translation = EuroCrossTranslation::factory()->for($otherShop, 'shop')->create();

        $this->getJson("/eurocross/translations/{$translation->id}")
            ->assertNotFound();
    }

    public function test_job_completes_translation_and_tracks_usage(): void
    {
        config()->set('services.openai.key', null);
        config()->set('services.deepl.key', null);

        $shop = EuroCrossSettings::factory()->create();
        $translation = EuroCrossTranslation::factory()->for($shop, 'shop')->create([
            'source_text' => 'Hello world',
            'source_locale' => 'en',
            'target_locale' => 'fr',
        ]);
        $job = EuroCrossTranslationJob::factory()->for($translation, 'translation')->create();

        (new ProcessPersistedTranslation($translation->id, $job->id))
            ->handle(app(TranslationService::class));

        $fresh = $translation->fresh();

        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->translated_text);
        $this->assertSame('mock', $fresh->provider);
        $this->assertSame('completed', $job->fresh()->status);
        $this->assertDatabaseCount('eurocross_translation_usage', 1);
    }

    public function test_failed_job_marks_translation_and_job_failed(): void
    {
        config()->set('services.openai.key', 'test-openai-key');
        config()->set('services.deepl.key', null);
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 500)]);

        $shop = EuroCrossSettings::factory()->create();
        $translation = EuroCrossTranslation::factory()->for($shop, 'shop')->create([
            'source_text' => 'Hello world',
            'source_locale' => 'en',
            'target_locale' => 'fr',
        ]);
        $job = EuroCrossTranslationJob::factory()->for($translation, 'translation')->create();
        $jobInstance = new ProcessPersistedTranslation($translation->id, $job->id);
        $exception = null;

        try {
            $jobInstance->handle(app(TranslationService::class));
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);

        $jobInstance->failed($exception);

        $this->assertSame('failed', $translation->fresh()->status);
        $this->assertSame(1, $translation->fresh()->retry_count);
        $this->assertSame('failed', $job->fresh()->status);
    }

    private function authenticate(string $token): EuroCrossSettings
    {
        $settings = EuroCrossSettings::factory()->create();

        EuroCrossApiKey::factory()
            ->for($settings, 'shop')
            ->accessToken($token)
            ->create();

        $this->withHeaders(['X-API-Key' => $token]);

        return $settings;
    }
}
