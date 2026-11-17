<?php

namespace App\Http\Controllers\EuroCross\Api;

use App\Http\Controllers\Controller;
use App\Models\EuroCrossApiKey;
use App\Models\EuroCrossAuditLog;
use App\Models\EuroCrossSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $settings = $this->shop($request);

        $keys = $settings->apiKeys()
            ->where('provider', '!=', 'eurocross')
            ->latest()
            ->get()
            ->map(fn (EuroCrossApiKey $key): array => $this->keyPayload($key));

        return response()->json([
            'success' => true,
            'data' => $keys,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $settings = $this->shop($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'key_name' => ['sometimes', 'string', 'max:80'],
            'provider' => ['required', 'string', 'in:openai,deepl'],
            'key' => ['required', 'string', 'max:4096'],
        ]);

        $name = $validated['name'] ?? $validated['key_name'] ?? $validated['provider'];

        $key = $settings->apiKeys()->create([
            'provider' => $validated['provider'],
            'key_name' => $name,
            'encrypted_key' => $validated['key'],
            'active' => true,
        ]);

        EuroCrossAuditLog::create([
            'shop_id' => $settings->id,
            'action' => 'provider_key_added',
            'entity_type' => 'eurocross_api_keys',
            'entity_id' => $key->id,
            'new_value' => $key->provider,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->keyPayload($key),
            'message' => 'API key saved. The secret value is not returned.',
        ], 201);
    }

    public function destroy(EuroCrossApiKey $apiKey, Request $request): JsonResponse
    {
        $settings = $this->shop($request);

        abort_unless($apiKey->shop_id === $settings->id, 404, 'API key not found.');

        $payload = $this->keyPayload($apiKey);
        $apiKey->delete();

        EuroCrossAuditLog::create([
            'shop_id' => $settings->id,
            'action' => 'provider_key_removed',
            'entity_type' => 'eurocross_api_keys',
            'entity_id' => $apiKey->id,
            'old_value' => $payload['provider'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key removed.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function keyPayload(EuroCrossApiKey $key): array
    {
        return [
            'id' => $key->id,
            'name' => $key->key_name,
            'key_name' => $key->key_name,
            'provider' => $key->provider,
            'active' => $key->active,
            'last_used_at' => $key->last_used_at?->toIso8601String(),
            'created_at' => $key->created_at?->toIso8601String(),
        ];
    }

    private function shop(Request $request): EuroCrossSettings
    {
        $settings = $request->attributes->get('eurocross_settings');

        abort_unless($settings instanceof EuroCrossSettings, 401, 'Authenticated shop context is missing.');

        return $settings;
    }
}
