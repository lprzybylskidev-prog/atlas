<?php

declare(strict_types=1);

namespace App\Shared\Application\DataLifecycle;

use InvalidArgumentException;

final readonly class DataLifecycleStepResult
{
    public function __construct(
        public string $step,
        public int $affectedRecords,
        public bool $idempotent,
    ) {
        if (trim($step) === '') {
            throw new InvalidArgumentException('Data lifecycle step must be a non-empty string.');
        }

        if ($affectedRecords < 0) {
            throw new InvalidArgumentException('Data lifecycle affected record count cannot be negative.');
        }
    }
}
