<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Contracts;

use App\Modules\Core\Calendar\Application\DTOs\CalendarEventInput;
use App\Modules\Core\Calendar\Domain\Events\PersonalCalendarEvent;
use DateTimeImmutable;

interface CalendarEventStore
{
    /** @return list<PersonalCalendarEvent> */
    public function forOwnerBefore(int $ownerUserId, DateTimeImmutable $rangeEndsAt): array;

    /** @param list<int> $ownerUserIds
     * @return list<PersonalCalendarEvent>
     */
    public function forOwnersBefore(array $ownerUserIds, DateTimeImmutable $rangeEndsAt): array;

    public function findOwned(string $eventPublicId, int $ownerUserId): ?PersonalCalendarEvent;

    public function create(string $publicId, int $ownerUserId, CalendarEventInput $input): void;

    public function update(string $eventPublicId, int $ownerUserId, int $expectedVersion, CalendarEventInput $input): bool;

    public function delete(string $eventPublicId, int $ownerUserId, int $expectedVersion): bool;

    public function upsertOccurrenceOverride(
        string $eventPublicId,
        int $ownerUserId,
        string $occurrenceDate,
        ?CalendarEventInput $input,
    ): void;

    /**
     * @param  list<string>  $eventPublicIds
     * @return array<string, array<string, CalendarEventInput|null>>
     */
    public function occurrenceOverrides(array $eventPublicIds): array;

    public function splitSeries(
        PersonalCalendarEvent $original,
        string $occurrenceDate,
        string $newPublicId,
        CalendarEventInput $futureInput,
    ): bool;
}
