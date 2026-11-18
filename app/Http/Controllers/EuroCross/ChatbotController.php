<?php

namespace App\Http\Controllers\EuroCross;

use App\Http\Controllers\Controller;
use App\Models\EuroCrossApiKey;
use App\Models\EuroCrossSettings;
use App\Services\ChatbotService;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChatbotController extends Controller
{
    private const RATE_LIMIT = 50;

    public function __construct(
        private ChatbotService $chatbotService,
        private TranslationService $translationService,
    ) {}

    public function message(Request $request): JsonResponse
    {
        $settings = $this->shop($request);

        if (! (bool) data_get(config("payment.plans.{$settings->plan}.features"), 'chatbot', false)) {
            return response()->json([
                'success' => false,
                'error' => 'The chatbot is not included in your plan. Upgrade to Growth or Scale to use it.',
            ], 403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'language' => ['sometimes', 'string', Rule::in($this->languageCodes())],
            'context' => ['sometimes', 'array'],
        ]);

        $visitorId = $this->visitorId($request);
        $service = $this->chatbotService->for($settings);

        if (! $service->checkRateLimit($visitorId, self::RATE_LIMIT)) {
            return response()->json([
                'success' => false,
                'error' => 'Chatbot rate limit exceeded. Try again later.',
            ], 429);
        }

        $result = $service->chat(
            (string) $validated['message'],
            (string) ($validated['language'] ?? 'en'),
            $validated['context'] ?? [],
        );

        if (! ($result['success'] ?? false)) {
            $status = ($result['code'] ?? null) === 'not_configured' ? 422 : 502;

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Chatbot service is unavailable.',
            ], $status);
        }

        $service->logConversation(
            $visitorId,
            (string) $validated['message'],
            (string) $result['reply'],
            (int) ($result['tokens_used'] ?? 0),
            (string) ($validated['language'] ?? 'en'),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'reply' => $result['reply'],
                'language' => $validated['language'] ?? 'en',
                'tokens_used' => (int) ($result['tokens_used'] ?? 0),
            ],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $visitorId = $this->visitorId($request);

        $hasStoredKey = $settings->apiKeys()
            ->where('provider', 'openai')
            ->where('active', true)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'is_configured' => $hasStoredKey || filled(config('services.openai.key')),
                'included' => (bool) data_get(config("payment.plans.{$settings->plan}.features"), 'chatbot', false),
                'plan' => $settings->plan,
                'rate_limit' => self::RATE_LIMIT,
                'remaining' => $this->chatbotService
                    ->for($settings)
                    ->remainingRateLimit($visitorId, self::RATE_LIMIT),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $settings = $this->shop($request);
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $messages = $this->chatbotService
            ->for($settings)
            ->history($this->visitorId($request), (int) ($validated['limit'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => $messages->map(fn ($message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'message' => $message->message,
                'language' => $message->language,
                'tokens' => $message->tokens,
                'created_at' => $message->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function languageCodes(): array
    {
        return array_column($this->translationService->languages(), 'code');
    }

    private function visitorId(Request $request): string
    {
        $apiKey = $request->attributes->get('eurocross_api_key');

        if ($apiKey instanceof EuroCrossApiKey) {
            return 'key:'.$apiKey->id;
        }

        return 'guest:'.hash('sha256', (string) $request->ip().' '.(string) $request->userAgent());
    }

    private function shop(Request $request): EuroCrossSettings
    {
        $settings = $request->attributes->get('eurocross_settings');

        abort_unless($settings instanceof EuroCrossSettings, 401, 'Authenticated shop context is missing.');

        return $settings;
    }
}
