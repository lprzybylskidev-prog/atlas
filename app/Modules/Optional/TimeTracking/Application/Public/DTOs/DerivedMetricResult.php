<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Public\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DerivedMetricResult
{
    /**
     * @param  list<string>  $sourceEventIds
     */
    public function __construct(
        public string $metricKey,
        public int $ruleVersion,
        public float $value,
        public array $sourceEventIds,
        public DateTimeImmutable $calculatedAt,
    ) {
        if (trim($metricKey) === '') {
            throw new InvalidArgumentException('Derived metric key must be a non-empty string.');
        }

        if ($ruleVersion < 1) {
            throw new InvalidArgumentException('Derived metric rule version must be positive.');
        }

        if (! is_finite($value)) {
            throw new InvalidArgumentException('Derived metric value must be finite.');
        }

        if ($sourceEventIds === []) {
            throw new InvalidArgumentException('Derived metric results must be traceable to at least one source event.');
        }

        foreach ($sourceEventIds as $sourceEventId) {
            if (trim($sourceEventId) === '') {
                throw new InvalidArgumentException('Derived metric source event IDs must be non-empty strings.');
            }
        }
    }
}
