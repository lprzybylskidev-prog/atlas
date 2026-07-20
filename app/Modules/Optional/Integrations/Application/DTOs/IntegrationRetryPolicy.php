<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\DTOs;

use InvalidArgumentException;

final readonly class IntegrationRetryPolicy
{
    public function __construct(
        public int $maxAttempts = 3,
        public int $baseDelayMilliseconds = 100,
        public int $timeoutMilliseconds = 5000,
        public int $circuitFailureThreshold = 3,
        public int $circuitOpenSeconds = 60,
    ) {
        if ($maxAttempts < 1 || $baseDelayMilliseconds < 0 || $timeoutMilliseconds < 1 || $circuitFailureThreshold < 1 || $circuitOpenSeconds < 1) {
            throw new InvalidArgumentException('Integration retry policy values must be positive.');
        }
    }
}
