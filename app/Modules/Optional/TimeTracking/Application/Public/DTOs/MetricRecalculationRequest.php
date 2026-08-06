<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Public\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MetricRecalculationRequest
{
    public function __construct(
        public string $metricKey,
        public int $ruleVersion,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public ?string $teamPublicId = null,
        public ?string $userPublicId = null,
    ) {
        if (trim($metricKey) === '') {
            throw new InvalidArgumentException('Metric recalculation key must be a non-empty string.');
        }

        if ($ruleVersion < 1) {
            throw new InvalidArgumentException('Metric recalculation rule version must be positive.');
        }

        if ($startsAt >= $endsAt) {
            throw new InvalidArgumentException('Metric recalculation start must precede end.');
        }
    }
}
