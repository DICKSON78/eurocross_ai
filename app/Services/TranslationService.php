<?php

namespace App\Services;

use App\Models\EuroCrossApiKey;
use App\Models\EuroCrossSettings;
use App\Utils\CircuitBreaker;
use Illuminate\Support\Facades\Http;
use Throwable;

class TranslationService
{
    /** @var array<string, string|null> */
    private array $keys = [];

    private ?EuroCrossSettings $shop = null;

    public function __construct()
    {
        $this->keys = $this->loadKeys();
    }

    public function for(EuroCrossSettings $shop): self
    {
        $service = clone $this;
        $service->shop = $shop;
        $service->keys = $service->loadKeys($shop);

        return $service;
    }

    public function isDemoMode(): bool
    {
        return empty($this->keys['openai']) && empty($this->keys['deepl']);
    }

    /**
     * @return array<int, array{code: string, name: string, native_name: string}>
     */
    public function languages(): array
    {
        return [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English'],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français'],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch'],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español'],
            ['code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano'],
            ['code' => 'pt', 'name' => 'Portuguese', 'native_name' => 'Português'],
            ['code' => 'nl', 'name' => 'Dutch', 'native_name' => 'Nederlands'],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية'],
            ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文'],
            ['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語'],
            ['code' => 'sv', 'name' => 'Swedish', 'native_name' => 'Svenska'],
            ['code' => 'da', 'name' => 'Danish', 'native_name' => 'Dansk'],
            ['code' => 'fi', 'name' => 'Finnish', 'native_name' => 'Suomi'],
            ['code' => 'pl', 'name' => 'Polish', 'native_name' => 'Polski'],
            ['code' => 'el', 'name' => 'Greek', 'native_name' => 'Ελληνικά'],
        ];
    }

    /**
     * Languages included in the shop's current plan.
     *
     * @return array<int, array{code: string, name: string, native_name: string}>
     */
    public function allowedLanguages(EuroCrossSettings $shop): array
    {
        $limit = $shop->planLanguageLimit();
        $languages = $this->languages();

        return $limit > 0 ? array_slice($languages, 0, $limit) : $languages;
    }

    /**
     * @return array<string, mixed>
     */
    public function translate(string $text, string $targetLang, ?string $sourceLang = 'en'): array
    {
        if ($this->isDemoMode()) {
            return $this->mockTranslate($text, $targetLang);
        }

        $providers = [];

        if ($this->keys['openai']) {
            $providers[] = 'openai';
        }

        if ($this->keys['deepl']) {
            $providers[] = 'deepl';
        }

        $errors = [];

        foreach ($providers as $provider) {
            $circuit = new CircuitBreaker('translation_'.$provider, 5, 60, 2);

            [$result, $error] = $circuit->execute(function () use ($provider, $text, $targetLang, $sourceLang) {
                return $provider === 'openai'
                    ? $this->translateViaOpenAI($text, $targetLang, $sourceLang)
                    : $this->translateViaDeepL($text, $targetLang, $sourceLang);
            });

            if ($error instanceof Throwable) {
                $errors[] = $provider.': '.$error->getMessage();

                continue;
            }

            if (is_array($result) && ($result['success'] ?? false)) {
                return $result;
            }

            $errors[] = $provider.': '.($result['error'] ?? 'Unknown translation error');
        }

        return [
            'success' => false,
            'error' => 'Translation providers unavailable. '.implode(' | ', $errors),
        ];
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<string, mixed>
     */
    public function batchTranslate(array $texts, string $targetLang, string $sourceLang = 'en'): array
    {
        $results = [];
        $errors = [];
        $totalTokens = 0;
        $totalCost = 0.0;
        $providers = [];

        foreach ($texts as $index => $text) {
            $result = $this->translate((string) $text, $targetLang, $sourceLang);
            $results[] = $result;

            if ($result['success'] ?? false) {
                $totalTokens += (int) ($result['tokens'] ?? 0);
                $totalCost += (float) ($result['cost'] ?? 0);
                $providers[$result['provider']] = true;
            } else {
                $errors[] = 'String '.$index.': '.($result['error'] ?? 'Translation failed');
            }
        }

        return [
            'success' => $errors === [],
            'results' => $results,
            'errors' => $errors,
            'total_texts' => count($texts),
            'total_tokens' => $totalTokens,
            'total_cost' => round($totalCost, 6),
            'provider' => $providers === [] ? 'none' : implode('+', array_keys($providers)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function translateViaOpenAI(string $text, string $targetLang, string $sourceLang): array
    {
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        $response = Http::connectTimeout(3)
            ->timeout(20)
            ->withToken((string) $this->keys['openai'])
            ->post($baseUrl.'/chat/completions', [
                'model' => config('services.openai.model', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => sprintf(
                            'You are a professional translator. Translate from %s to %s. Return only the translated text without notes or quotes.',
                            $sourceLang ?: 'auto-detect',
                            $targetLang,
                        ),
                    ],
                    ['role' => 'user', 'content' => $text],
                ],
                'temperature' => 0.2,
            ])->throw();

        $translated = $response->json('choices.0.message.content');

        if (! is_string($translated) || $translated === '') {
            return ['success' => false, 'error' => 'OpenAI returned an empty translation'];
        }

        $tokens = (int) ($response->json('usage.total_tokens') ?? max(1, (int) ceil(mb_strlen($text) / 4)));
        $costPerMillion = (float) config('services.openai.cost_per_million_tokens', 0.15);

        return [
            'success' => true,
            'translated' => $translated,
            'provider' => 'openai',
            'tokens' => $tokens,
            'cost' => round(($tokens / 1_000_000) * $costPerMillion, 6),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function translateViaDeepL(string $text, string $targetLang, string $sourceLang): array
    {
        $langMap = [
            'en' => 'EN', 'fr' => 'FR', 'de' => 'DE', 'es' => 'ES', 'it' => 'IT',
            'pt' => 'PT', 'nl' => 'NL', 'ar' => 'AR', 'zh' => 'ZH', 'ja' => 'JA',
        ];

        $response = Http::connectTimeout(3)
            ->timeout(20)
            ->withHeaders([
                'Authorization' => 'DeepL-Auth-Key '.$this->keys['deepl'],
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])
            ->asForm()
            ->post((string) config('services.deepl.api_url'), [
                'text' => $text,
                'target_lang' => $langMap[$targetLang] ?? strtoupper($targetLang),
                'source_lang' => in_array($sourceLang, array_keys($langMap), true)
                    ? $langMap[$sourceLang]
                    : null,
            ])->throw();

        $translated = $response->json('translations.0.text');

        if (! is_string($translated) || $translated === '') {
            return ['success' => false, 'error' => 'DeepL returned an empty translation'];
        }

        $characters = mb_strlen($text);
        $costPerMillion = (float) config('services.deepl.cost_per_million_characters', 25.00);

        return [
            'success' => true,
            'translated' => $translated,
            'provider' => 'deepl',
            'tokens' => max(1, (int) ceil($characters / 4)),
            'characters' => $characters,
            'cost' => round(($characters / 1_000_000) * $costPerMillion, 6),
        ];
    }

    /**
     * Demo fallback used only when no AI provider is configured.
     *
     * @return array<string, mixed>
     */
    private function mockTranslate(string $text, string $targetLang): array
    {
        $language = collect($this->languages())->firstWhere('code', $targetLang);

        return [
            'success' => true,
            'translated' => '['.($language['name'] ?? strtoupper($targetLang)).'] '.$text,
            'provider' => 'mock',
            'demo' => true,
            'tokens' => max(1, (int) ceil(mb_strlen($text) / 4)),
            'cost' => 0,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function loadKeys(?EuroCrossSettings $shop = null): array
    {
        $query = EuroCrossApiKey::query()
            ->whereIn('provider', ['openai', 'deepl'])
            ->where('active', true);

        if ($shop !== null) {
            $query->where('shop_id', $shop->id);
        }

        $stored = $query->get()->keyBy('provider');

        return [
            'openai' => $stored->get('openai')?->encrypted_key ?: config('services.openai.key'),
            'deepl' => $stored->get('deepl')?->encrypted_key ?: config('services.deepl.key'),
        ];
    }
}
