<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Public\DTOs;

readonly class RetryPolicy
{
    public function __construct(
        public bool $retryable,
        public int $maxAttempts = 1,
        public int $backoffSeconds = 60,
    ) {}

    /**
     * @return array{retryable: bool, max_attempts: int, backoff_seconds: int}
     */
    public function toArray(): array
    {
        return [
            'retryable' => $this->retryable,
            'max_attempts' => $this->maxAttempts,
            'backoff_seconds' => $this->backoffSeconds,
        ];
    }
}
