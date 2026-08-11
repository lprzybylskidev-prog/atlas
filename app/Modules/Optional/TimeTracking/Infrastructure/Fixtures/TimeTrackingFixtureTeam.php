<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Fixtures;

final readonly class TimeTrackingFixtureTeam
{
    public function __construct(
        public int $internalId,
        public string $publicId,
        public string $name,
    ) {}
}
