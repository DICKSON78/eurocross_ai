<?php

namespace App\Http\Middleware;

use App\Models\EuroCrossApiKey;
use App\Models\EuroCrossSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EuroCrossRateLimit
{
    /**
     * Paths that may be called while no trial or subscription is active.
     *
     * @var array<int, string>
     */
    private const ACCESS_EXEMPT_PATHS = [
        'eurocross/settings',
        'eurocross/trial/start',
        'eurocross/checkout',
        'eurocross/api-keys',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (! $token || ! str_starts_with($token, 'ecx_live_')) {
            return $this->error('A valid X-API-Key header is required.', 401);
        }

        $apiKey = EuroCrossApiKey::with('shop')
            ->where('key_hash', hash('sha256', $token))
            ->where('active', true)
            ->first();

        if (! $apiKey instanceof EuroCrossApiKey || ! $apiKey->shop instanceof EuroCrossSettings) {
            return $this->error('Invalid or revoked API key.', 401);
        }

        $settings = $apiKey->shop;

        if (! $this->pathIsAccessExempt($request->path()) && ! $settings->hasActiveAccess()) {
            return $this->error($settings->accessStatus()['reason'], 403);
        }

        $limit = $settings->rateLimit();
        $rateKey = 'eurocross:api:'.$apiKey->id;

        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded. Try again in '.$seconds.' seconds.',
            ], 429, [
                'Retry-After' => $seconds,
                'X-RateLimit-Limit' => $limit,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($rateKey, 60);
        $apiKey->markUsed();

        $request->attributes->set('eurocross_api_key', $apiKey);
        $request->attributes->set('eurocross_settings', $settings);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, RateLimiter::remaining($rateKey, $limit)));

        return $response;
    }

    private function extractToken(Request $request): ?string
    {
        $token = $request->header('X-API-Key');

        if (! $token) {
            $token = $request->bearerToken();
        }

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function pathIsAccessExempt(string $path): bool
    {
        if (in_array($path, self::ACCESS_EXEMPT_PATHS, true)) {
            return true;
        }

        return str_starts_with($path, 'eurocross/trial/');
    }

    private function error(string $message, int $status): Response
    {
        return response()->json([
            'success' => false,
            'error' => $message,
        ], $status);
    }
}
