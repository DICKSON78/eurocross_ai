<?php

namespace Tests\Unit;

use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_translate_returns_success(): void
    {
        $service = new TranslationService;

        // Mock translation (no API key)
        $result = $service->translate('Hello World', 'fr', 'en');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('translated', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertEquals('mock', $result['provider']);
    }

    public function test_batch_translate_returns_results(): void
    {
        $service = new TranslationService;

        $texts = ['Hello', 'World', 'Test'];
        $result = $service->batchTranslate($texts, 'fr', 'en');

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['total_texts']);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('total_tokens', $result);
        $this->assertArrayHasKey('total_cost', $result);
    }

    public function test_mock_translate_includes_language_name(): void
    {
        $service = new TranslationService;

        $result = $service->translate('Hello', 'fr', 'en');

        $this->assertStringContainsString('[French]', $result['translated']);
    }
}
