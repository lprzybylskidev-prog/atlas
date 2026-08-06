<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Public\DTOs;

use DateTimeImmutable;

final readonly class MetricDefinitionSnapshot
{
    public function __construct(
        public MetricDefinition $definition,
        public DateTimeImmutable $capturedAt,
    ) {}
}
