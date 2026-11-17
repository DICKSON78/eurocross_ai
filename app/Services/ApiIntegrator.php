<?php

namespace App\Services;

use App\Utils\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApiIntegrator
{
    private string $baseUrl;

    private ?string $apiKey;

    private int $timeout = 20;

    private int $connectTimeout = 3;

    private int $maxRetries = 3;

    private ?CircuitBreaker $circuitBreaker = null;

    public function __construct(string $baseUrl, ?string $apiKey = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    public function setCircuitBreaker(CircuitBreaker $circuitBreaker): self
    {
        $this->circuitBreaker = $circuitBreaker;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function request(string $method, string $endpoint, array $options = []): array
    {
        $url = $this->baseUrl.'/'.ltrim($endpoint, '/');
        $headers = array_merge($this->defaultHeaders(), $options['headers'] ?? []);
        unset($options['headers']);

        $lastError = 'API request failed';

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            $send = function () use ($method, $url, $options, $headers) {
                return Http::withHeaders($headers)
                    ->connectTimeout($this->connectTimeout)
                    ->timeout($this->timeout)
                    ->send(strtoupper($method), $url, $options);
            };

            if ($this->circuitBreaker !== null) {
                [$response, $error] = $this->circuitBreaker->execute($send);
            } else {
                try {
                    $response = $send();
                    $error = null;
                } catch (Throwable $exception) {
                    $response = null;
                    $error = $exception;
                }
            }

            if ($error instanceof Throwable) {
                $lastError = $error->getMessage();
                if ($this->circuitBreaker === null || $this->circuitBreaker->isAvailable()) {
                    $this->backoff($attempt);

                    continue;
                }

                break;
            }

            if ($response === null) {
                break;
            }

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'headers' => $response->headers(),
                ];
            }

            $lastError = 'HTTP '.$response->status().': '.$response->body();

            if (! in_array($response->status(), [429, 500, 502, 503, 504], true)) {
                return [
                    'success' => false,
                    'error' => $lastError,
                    'status' => $response->status(),
                ];
            }

            if ($response->status() === 429) {
                $retryAfter = (int) ($response->header('Retry-After') ?? 60);
                $lastError = 'Rate limited by upstream. Retry after '.$retryAfter.' seconds.';
            }

            $this->backoff($attempt);
        }

        Log::channel('api')->error('ApiIntegrator request failed', [
            'url' => $url,
            'error' => $lastError,
        ]);

        return ['success' => false, 'error' => $lastError];
    }

    public function logUsage(string $provider, int $tokens, float $cost, string $endpoint): void
    {
        $key = 'eurocross:api_usage:'.$provider.':'.now()->toDateString();
        $usage = Cache::get($key, []);
        $usage[] = [
            'endpoint' => $endpoint,
            'tokens' => $tokens,
            'cost' => $cost,
            'recorded_at' => now()->toIso8601String(),
        ];
        Cache::put($key, $usage, 86400);

        Log::channel('api')->info('API usage recorded', [
            'provider' => $provider,
            'tokens' => $tokens,
            'cost' => $cost,
            'endpoint' => $endpoint,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubprocessorInfo(): array
    {
        return [
            'primary_provider' => 'OpenAI',
            'fallback_provider' => 'DeepL',
            'purpose' => 'Translation and chatbot services',
            'data_retention' => 'No prompt content is persisted by EuroCross AI; provider retention follows their DPA.',
            'regions' => ['US', 'EU'],
            'data_processing_agreement' => 'Available from each provider on request',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function defaultHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'EuroCross-AI/1.0',
        ];

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$this->apiKey;
        }

        return $headers;
    }

    private function backoff(int $attempt): void
    {
        if ($attempt >= $this->maxRetries) {
            return;
        }

        usleep((int) (250_000 * (2 ** $attempt)));
    }
}
