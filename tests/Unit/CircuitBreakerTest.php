<?php

namespace Tests\Unit;

use App\Utils\CircuitBreaker;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    public function test_circuit_breaker_starts_closed(): void
    {
        $cb = new CircuitBreaker('test', 3, 60, 2);
        $this->assertEquals('closed', $cb->getState());
    }

    public function test_circuit_breaker_opens_after_failures(): void
    {
        $cb = new CircuitBreaker('test', 3, 60, 2);

        // Trigger 3 failures
        for ($i = 0; $i < 3; $i++) {
            $cb->execute(function () {
                throw new \Exception('Test failure');
            });
        }

        $this->assertEquals('open', $cb->getState());
    }

    public function test_circuit_breaker_allows_success(): void
    {
        $cb = new CircuitBreaker('test', 3, 60, 2);

        [$result, $error] = $cb->execute(function () {
            return 'success';
        });

        $this->assertEquals('success', $result);
        $this->assertNull($error);
        $this->assertEquals('closed', $cb->getState());
    }

    public function test_circuit_breaker_tracks_failure_count(): void
    {
        $cb = new CircuitBreaker('test', 3, 60, 2);

        $cb->execute(function () {
            throw new \Exception('Test failure');
        });

        $this->assertEquals(1, $cb->getFailureCount());
    }
}
