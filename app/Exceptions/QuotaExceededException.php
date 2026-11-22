<?php

namespace App\Exceptions;

use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public function __construct(
        public int $used,
        public int $limit,
        public int $requested,
        public int $reserved,
    ) {
        parent::__construct('Monthly word allowance exceeded. Upgrade your plan, or wait for your next monthly quota reset.');
    }

    /**
     * @return array{used: int, limit: int, requested: int, reserved: int}
     */
    public function context(): array
    {
        return [
            'used' => $this->used,
            'limit' => $this->limit,
            'requested' => $this->requested,
            'reserved' => $this->reserved,
        ];
    }
}
