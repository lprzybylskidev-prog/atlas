<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Infrastructure\Persistence;

use App\Modules\Core\Calendar\Application\Contracts\CalendarContributionStore;
use App\Modules\Core\Calendar\Application\Public\Contracts\CalendarEventPublisher;
use App\Modules\Core\Calendar\Application\Public\DTOs\CalendarEventContribution;
use App\Modules\Core\Calendar\Application\Public\DTOs\FreeBusyWindow;
use App\Modules\Core\Calendar\Infrastructure\Persistence\TableNames\CalendarDatabaseTable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use UnexpectedValueException;

final readonly class DatabaseCalendarContributionStore implements CalendarContributionStore, CalendarEventPublisher
{
    public function __construct(private ConnectionInterface $database) {}

    public function upsert(CalendarEventContribution $event): void
    {
        $this->database->table(CalendarDatabaseTable::CONTRIBUTED_EVENTS)->updateOrInsert(
            [
                'source_module' => $event->sourceModule,
                'source_event_public_id' => $event->sourceEventPublicId,
            ],
            [
                'title' => $event->title,
                'description' => $event->description,
                'starts_at' => $event->startsAt->format(DATE_ATOM),
                'ends_at' => $event->endsAt->format(DATE_ATOM),
                'all_day' => $event->allDay,
                'location' => $event->location,
                'participant_user_public_ids' => json_encode($event->participantUserPublicIds, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function remove(string $sourceModule, string $sourceEventPublicId): void
    {
        $this->database->table(CalendarDatabaseTable::CONTRIBUTED_EVENTS)
            ->where('source_module', $sourceModule)
            ->where('source_event_public_id', $sourceEventPublicId)
            ->delete();
    }

    public function busyWindows(array $userPublicIds, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array
    {
        if ($userPublicIds === []) {
            return [];
        }

        $rows = $this->database->table(CalendarDatabaseTable::CONTRIBUTED_EVENTS)
            ->where('starts_at', '<', $endsAt->format(DATE_ATOM))
            ->where('ends_at', '>', $startsAt->format(DATE_ATOM))
            ->get(['starts_at', 'ends_at', 'participant_user_public_ids']);
        $requested = array_fill_keys($userPublicIds, true);
        $windows = [];

        foreach ($rows as $row) {
            $participants = $this->stringList($row->participant_user_public_ids);

            foreach ($participants as $participant) {
                if (! isset($requested[$participant])) {
                    continue;
                }

                $windows[] = new FreeBusyWindow(
                    $participant,
                    new DateTimeImmutable($this->requiredString($row->starts_at)),
                    new DateTimeImmutable($this->requiredString($row->ends_at)),
                );
            }
        }

        return $windows;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value;

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }

    private function requiredString(mixed $value): string
    {
        if (! is_string($value)) {
            throw new UnexpectedValueException('Calendar contribution persistence returned a non-string timestamp.');
        }

        return $value;
    }
}
