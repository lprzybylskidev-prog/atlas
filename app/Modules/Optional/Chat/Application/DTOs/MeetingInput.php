<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Meetings\MeetingMode;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecurrence;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MeetingInput
{
    /**
     * @param  list<string>  $inviteePublicIds
     * @param  list<int>  $reminderMinutes
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public MeetingMode $mode,
        public ?string $location,
        public ?MeetingRecurrence $recurrence,
        public array $inviteePublicIds,
        public array $reminderMinutes = [15],
    ) {
        if (trim($title) === '' || mb_strlen($title) > 200 || $endsAt <= $startsAt) {
            throw new InvalidArgumentException('Meeting title and time range are invalid.');
        }
        if ($mode->requiresLocation() && trim((string) $location) === '') {
            throw new InvalidArgumentException('A physical location is required for in-person and hybrid Meetings.');
        }
        if (! $mode->requiresLocation() && $location !== null) {
            throw new InvalidArgumentException('Online Meetings do not have a physical location.');
        }
        if (count($inviteePublicIds) !== count(array_unique($inviteePublicIds))) {
            throw new InvalidArgumentException('Meeting invitees must be unique.');
        }
    }
}
