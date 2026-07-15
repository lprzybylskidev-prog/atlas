<?php

declare(strict_types=1);

namespace App\Shared\Application\DataLifecycle;

use InvalidArgumentException;

final readonly class DataLifecycleImpact
{
    public function __construct(
        public string $dataSet,
        public int $estimatedRecords,
        public bool $irreversible,
    ) {
        if (trim($dataSet) === '') {
            throw new InvalidArgumentException('Data lifecycle data set must be a non-empty string.');
        }

        if ($estimatedRecords < 0) {
            throw new InvalidArgumentException('Data lifecycle estimated record count cannot be negative.');
        }
    }
}
