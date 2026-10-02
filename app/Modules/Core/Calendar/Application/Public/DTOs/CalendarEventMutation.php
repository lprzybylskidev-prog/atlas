<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Public\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CalendarEventMutation
{
    public function __construct(
        public string $effectiveDate,
        public string $scope,
        public bool $cancelled,
        public ?string $title = null,
        public ?string $description = null,
        public ?DateTimeImmutable $startsAt = null,
        public ?DateTimeImmutable $endsAt = null,
        public ?string $location = null,
        public ?string $mode = null,
    ) {
        if (! in_array($scope, ['occurrence', 'future'], true)) {
            throw new InvalidArgumentException('Calendar contribution mutation scope is invalid.');
        }
    }
}
