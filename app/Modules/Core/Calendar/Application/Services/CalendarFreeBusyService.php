<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Services;

use App\Modules\Core\Calendar\Application\Contracts\CalendarContributionStore;
use App\Modules\Core\Calendar\Application\Contracts\CalendarEventStore;
use App\Modules\Core\Calendar\Application\Public\Contracts\FreeBusyLookup;
use App\Modules\Core\Calendar\Application\Public\DTOs\FreeBusyConflict;
use App\Modules\Core\Calendar\Application\Public\DTOs\FreeBusyQuery;
use App\Modules\Core\Calendar\Application\Public\DTOs\FreeBusyWindow;
use App\Modules\Core\Calendar\Domain\Events\CalendarAvailability;
use App\Modules\Core\Calendar\Domain\Events\PersonalCalendarEvent;
use App\Modules\Core\Calendar\Domain\Events\RecurrenceExpander;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;

final readonly class CalendarFreeBusyService implements FreeBusyLookup
{
    public function __construct(
        private CalendarEventStore $events,
        private CalendarContributionStore $contributions,
        private UserLookup $users,
        private RecurrenceExpander $recurrence,
    ) {}

    public function windows(FreeBusyQuery $query): array
    {
        $ownerIds = $this->users->internalIdsForPublicIds($query->userPublicIds);
        $publicIdsByInternalId = array_flip($ownerIds);
        $windows = $this->contributions->busyWindows($query->userPublicIds, $query->startsAt, $query->endsAt);

        $events = $this->events->forOwnersBefore(array_values($ownerIds), $query->endsAt);
        $overrides = $this->events->occurrenceOverrides(array_map(
            static fn (PersonalCalendarEvent $event): string => $event->publicId,
            $events,
        ));

        foreach ($events as $event) {

            $userPublicId = $publicIdsByInternalId[$event->ownerUserId] ?? null;
            if (! is_string($userPublicId)) {
                continue;
            }

            foreach ($this->recurrence->expand($event, $query->startsAt, $query->endsAt) as $occurrence) {
                $hasOverride = array_key_exists($occurrence->occurrenceDate, $overrides[$event->publicId] ?? []);
                if ($hasOverride) {
                    $override = $overrides[$event->publicId][$occurrence->occurrenceDate];
                    if ($override === null || $override->availability !== CalendarAvailability::Busy) {
                        continue;
                    }
                    $windows[] = new FreeBusyWindow($userPublicId, $override->startsAt, $override->endsAt);
                } elseif ($event->availability === CalendarAvailability::Busy) {
                    $windows[] = new FreeBusyWindow($userPublicId, $occurrence->startsAt, $occurrence->endsAt);
                }
            }
        }

        usort($windows, static fn (FreeBusyWindow $left, FreeBusyWindow $right): int => [
            $left->userPublicId,
            $left->startsAt,
        ] <=> [
            $right->userPublicId,
            $right->startsAt,
        ]);

        return $windows;
    }

    public function conflicts(FreeBusyQuery $query): array
    {
        $counts = array_fill_keys($query->userPublicIds, 0);

        foreach ($this->windows($query) as $window) {
            $counts[$window->userPublicId]++;
        }

        $conflicts = [];
        foreach ($counts as $userPublicId => $count) {
            if ($count > 0) {
                $conflicts[] = new FreeBusyConflict($userPublicId, $count);
            }
        }

        return $conflicts;
    }
}
