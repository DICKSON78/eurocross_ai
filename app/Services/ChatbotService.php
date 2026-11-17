<?php

namespace App\Services;

use App\Models\EuroCrossChatMessage;
use App\Models\EuroCrossSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class ChatbotService
{
    private ?EuroCrossSettings $shop = null;

    public function for(EuroCrossSettings $shop): self
    {
        $service = clone $this;
        $service->shop = $shop;

        return $service;
    }

    /**
     * @return array<string, mixed>
     */
    public function chat(string $message, string $language = 'en', ?array $context = null): array
    {
        $apiKey = $this->apiKey();

        if (! $apiKey) {
            return [
                'success' => false,
                'code' => 'not_configured',
                'error' => 'Chatbot is not configured. Add an OpenAI API key first.',
            ];
        }

        try {
            $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

            $response = Http::connectTimeout(3)
                ->timeout(20)
                ->withToken($apiKey)
                ->post($baseUrl.'/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->buildSystemPrompt($language, $context)],
                        ['role' => 'user', 'content' => $message],
                    ],
                    'max_tokens' => 500,
                    'temperature' => 0.7,
                ])->throw();

            $reply = $response->json('choices.0.message.content');

            if (! is_string($reply) || $reply === '') {
                return ['success' => false, 'error' => 'Chatbot returned an empty reply'];
            }

            return [
                'success' => true,
                'reply' => $reply,
                'language' => $language,
                'tokens_used' => (int) ($response->json('usage.total_tokens') ?? 0),
            ];
        } catch (Throwable $exception) {
            Log::warning('Chatbot request failed', ['error' => $exception->getMessage()]);

            return ['success' => false, 'error' => 'Chatbot service is unavailable. Try again shortly.'];
        }
    }

    public function checkRateLimit(string $visitorId, int $limit = 50): bool
    {
        $key = $this->rateLimitKey($visitorId);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return false;
        }

        RateLimiter::hit($key, 3600);

        return true;
    }

    public function remainingRateLimit(string $visitorId, int $limit = 50): int
    {
        return RateLimiter::remaining($this->rateLimitKey($visitorId), $limit);
    }

    public function logConversation(
        string $visitorId,
        string $message,
        string $reply,
        int $tokens,
        string $language = 'en',
    ): void {
        $shop = $this->shop ?? EuroCrossSettings::first();

        if (! $shop instanceof EuroCrossSettings) {
            return;
        }

        EuroCrossChatMessage::create([
            'shop_id' => $shop->id,
            'visitor_id' => $visitorId,
            'language' => $language,
            'role' => 'user',
            'message' => $message,
            'tokens' => 0,
        ]);

        EuroCrossChatMessage::create([
            'shop_id' => $shop->id,
            'visitor_id' => $visitorId,
            'language' => $language,
            'role' => 'assistant',
            'message' => $reply,
            'tokens' => $tokens,
        ]);
    }

    /**
     * @return Collection<int, EuroCrossChatMessage>
     */
    public function history(string $visitorId, int $limit = 20)
    {
        $shop = $this->shop ?? EuroCrossSettings::first();

        if (! $shop instanceof EuroCrossSettings) {
            return new Collection;
        }

        return EuroCrossChatMessage::query()
            ->where('shop_id', $shop->id)
            ->where('visitor_id', $visitorId)
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    private function buildSystemPrompt(string $language, ?array $context = null): string
    {
        $prompt = 'You are a helpful customer service chatbot for EuroCross AI. '
            .'Respond in '.$language.'. Be concise, accurate, and professional.';

        if ($context !== []) {
            $prompt .= "\nStore context: ".json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        return $prompt;
    }

    private function apiKey(): ?string
    {
        if ($this->shop !== null) {
            $apiKeyModel = $this->shop->apiKeys()
                ->where('provider', 'openai')
                ->where('active', true)
                ->first();

            if ($apiKeyModel !== null) {
                return (string) $apiKeyModel->encrypted_key;
            }
        }

        $configured = config('services.openai.key');

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    private function rateLimitKey(string $visitorId): string
    {
        return 'eurocross:chat:'.hash('sha256', $visitorId);
    }
}
