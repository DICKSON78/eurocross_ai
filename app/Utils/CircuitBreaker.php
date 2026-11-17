<?php

namespace App\Utils;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CircuitBreaker
{
    public const CLOSED = 'closed';

    public const OPEN = 'open';

    public const HALF_OPEN = 'half_open';

    protected string $name;

    protected int $failureThreshold;

    protected int $resetTimeout;

    protected int $halfOpenMaxCalls;

    protected string $state = self::CLOSED;

    protected int $failureCount = 0;

    protected ?int $lastFailureAt = null;

    protected int $successCount = 0;

    public function __construct(
        string $name,
        int $failureThreshold = 5,
        int $resetTimeout = 60,
        int $halfOpenMaxCalls = 3,
    ) {
        $this->name = $name;
        $this->failureThreshold = $failureThreshold;
        $this->resetTimeout = $resetTimeout;
        $this->halfOpenMaxCalls = $halfOpenMaxCalls;
        $this->loadState();
    }

    /**
     * @param  callable(): mixed  $callback
     * @return array{0: mixed, 1: Throwable|null}
     */
    public function execute(callable $callback): array
    {
        if ($this->state === self::OPEN && ! $this->shouldAttemptReset()) {
            $seconds = max(0, ($this->lastFailureAt ?? 0) + $this->resetTimeout - now()->getTimestamp());

            return [null, new \RuntimeException(
                "Circuit breaker [{$this->name}] is open. Retry in {$seconds} seconds."
            )];
        }

        if ($this->state === self::OPEN) {
            $this->state = self::HALF_OPEN;
            $this->persistState();
            Log::info("Circuit breaker [{$this->name}] entering half-open state");
        }

        try {
            $result = $callback();
            $this->onSuccess();

            return [$result, null];
        } catch (Throwable $exception) {
            $this->onFailure();

            return [null, $exception];
        }
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function isAvailable(): bool
    {
        return $this->state !== self::OPEN || $this->shouldAttemptReset();
    }

    public function reset(): void
    {
        Cache::forget($this->cacheKey());
        $this->state = self::CLOSED;
        $this->failureCount = 0;
        $this->lastFailureAt = null;
        $this->successCount = 0;
    }

    private function onSuccess(): void
    {
        $this->failureCount = 0;
        $this->lastFailureAt = null;

        if ($this->state === self::HALF_OPEN) {
            $this->successCount++;

            if ($this->successCount >= $this->halfOpenMaxCalls) {
                $this->state = self::CLOSED;
                $this->successCount = 0;
                Log::info("Circuit breaker [{$this->name}] closed after successful recovery");
            }
        }

        $this->persistState();
    }

    private function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureAt = now()->getTimestamp();

        if ($this->state === self::HALF_OPEN) {
            $this->state = self::OPEN;
            $this->successCount = 0;
            Log::warning("Circuit breaker [{$this->name}] reopened from half-open state");
        } elseif ($this->state === self::CLOSED && $this->failureCount >= $this->failureThreshold) {
            $this->state = self::OPEN;
            Log::warning("Circuit breaker [{$this->name}] opened after repeated failures", [
                'failure_count' => $this->failureCount,
                'threshold' => $this->failureThreshold,
            ]);
        }

        $this->persistState();
    }

    private function shouldAttemptReset(): bool
    {
        if ($this->lastFailureAt === null) {
            return true;
        }

        return (now()->getTimestamp() - $this->lastFailureAt) >= $this->resetTimeout;
    }

    private function cacheKey(): string
    {
        return 'eurocross:circuit:'.$this->name;
    }

    private function loadState(): void
    {
        $state = Cache::get($this->cacheKey());

        if (! is_array($state)) {
            return;
        }

        $this->state = in_array(data_get($state, 'state'), [self::CLOSED, self::OPEN, self::HALF_OPEN], true)
            ? (string) data_get($state, 'state')
            : self::CLOSED;
        $this->failureCount = (int) data_get($state, 'failure_count', 0);
        $this->lastFailureAt = data_get($state, 'last_failure_at');
        $this->successCount = (int) data_get($state, 'success_count', 0);
    }

    private function persistState(): void
    {
        Cache::put($this->cacheKey(), [
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'last_failure_at' => $this->lastFailureAt,
            'success_count' => $this->successCount,
        ], max(3600, $this->resetTimeout * 2));
    }
}
