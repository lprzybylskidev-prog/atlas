<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Public\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CalendarEventContribution
{
    /**
     * @param  list<string>  $participantUserPublicIds
     * @param  list<CalendarEventMutation>  $mutations
     */
    public function __construct(
        public string $sourceModule,
        public string $sourceEventPublicId,
        public string $title,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public bool $allDay,
        public array $participantUserPublicIds,
        public ?string $description = null,
        public ?string $location = null,
        public ?CalendarEventRecurrence $recurrence = null,
        public ?string $kind = null,
        public ?string $mode = null,
        public ?string $deepLinkUrl = null,
        public bool $cancelled = false,
        public array $mutations = [],
    ) {
        if (trim($sourceModule) === '' || trim($sourceEventPublicId) === '' || trim($title) === '') {
            throw new InvalidArgumentException('Calendar contribution source, source event public ID, and title are required.');
        }

        if ($endsAt <= $startsAt) {
            throw new InvalidArgumentException('Calendar contribution end must be after its start.');
        }

        if ($participantUserPublicIds === [] || count($participantUserPublicIds) !== count(array_unique($participantUserPublicIds))) {
            throw new InvalidArgumentException('Calendar contribution participants must be non-empty and unique.');
        }

        if ($kind !== null && trim($kind) === '') {
            throw new InvalidArgumentException('Calendar contribution kind cannot be empty.');
        }
    }
}
