<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Calendar;

use App\Modules\Core\Calendar\Application\Public\Contracts\CalendarEventPublisher;
use App\Modules\Core\Calendar\Application\Public\DTOs\CalendarEventContribution;

final readonly class MeetingCalendarPublisher
{
    public function __construct(private CalendarEventPublisher $calendar) {}

    public function upsert(CalendarEventContribution $meetingEvent): void
    {
        $this->calendar->upsert($meetingEvent);
    }

    public function remove(string $meetingPublicId): void
    {
        $this->calendar->remove('chat', $meetingPublicId);
    }
}
