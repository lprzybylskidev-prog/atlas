<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Infrastructure\Persistence;

use App\Modules\Core\Calendar\Application\Contracts\CalendarContributionStore;
use App\Modules\Core\Calendar\Application\Public\Contracts\CalendarEventPublisher;
use App\Modules\Core\Calendar\Application\Public\DTOs\CalendarEventContribution;
use App\Modules\Core\Calendar\Application\Public\DTOs\CalendarEventMutation;
use App\Modules\Core\Calendar\Application\Public\DTOs\CalendarEventRecurrence;
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
                'recurrence_frequency' => $event->recurrence?->frequency,
                'recurrence_weekdays' => $event->recurrence === null ? null : json_encode($event->recurrence->weekdays, JSON_THROW_ON_ERROR),
                'recurrence_ends_on' => $event->recurrence?->endsOn?->format('Y-m-d'),
                'recurrence_count' => $event->recurrence?->occurrenceCount,
                'kind' => $event->kind,
                'mode' => $event->mode,
                'deep_link_url' => $event->deepLinkUrl,
                'cancelled' => $event->cancelled,
                'recurrence_mutations' => json_encode(array_map(static fn ($mutation): array => [
                    'effective_date' => $mutation->effectiveDate, 'scope' => $mutation->scope, 'cancelled' => $mutation->cancelled,
                    'title' => $mutation->title, 'description' => $mutation->description,
                    'starts_at' => $mutation->startsAt?->format(DATE_ATOM), 'ends_at' => $mutation->endsAt?->format(DATE_ATOM),
                    'location' => $mutation->location, 'mode' => $mutation->mode,
                ], $event->mutations), JSON_THROW_ON_ERROR),
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

    public function visibleFor(string $userPublicId, DateTimeImmutable $rangeEndsAt): array
    {
        $rows = $this->database->table(CalendarDatabaseTable::CONTRIBUTED_EVENTS)
            ->where('starts_at', '<', $rangeEndsAt->format(DATE_ATOM))->orderBy('starts_at')->get();
        $events = [];
        foreach ($rows as $row) {
            $participants = $this->stringList($row->participant_user_public_ids);
            if (! in_array($userPublicId, $participants, true)) {
                continue;
            }
            $frequency = is_string($row->recurrence_frequency) ? $row->recurrence_frequency : null;
            $events[] = new CalendarEventContribution(
                sourceModule: $this->requiredString($row->source_module), sourceEventPublicId: $this->requiredString($row->source_event_public_id),
                title: $this->requiredString($row->title), startsAt: new DateTimeImmutable($this->requiredString($row->starts_at)),
                endsAt: new DateTimeImmutable($this->requiredString($row->ends_at)), allDay: (bool) $row->all_day,
                participantUserPublicIds: $participants, description: is_string($row->description) ? $row->description : null,
                location: is_string($row->location) ? $row->location : null,
                recurrence: $frequency === null ? null : new CalendarEventRecurrence(
                    $frequency, $this->intList($row->recurrence_weekdays), is_string($row->recurrence_ends_on) ? new DateTimeImmutable($row->recurrence_ends_on) : null,
                    is_numeric($row->recurrence_count) ? (int) $row->recurrence_count : null,
                ), kind: is_string($row->kind) ? $row->kind : null, mode: is_string($row->mode) ? $row->mode : null,
                deepLinkUrl: is_string($row->deep_link_url) ? $row->deep_link_url : null, cancelled: (bool) $row->cancelled,
                mutations: $this->mutations($row->recurrence_mutations),
            );
        }

        return $events;
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

    /** @return list<int> */
    private function intList(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value;

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): int => is_numeric($item) ? (int) $item : 0, $decoded));
    }

    /** @return list<CalendarEventMutation> */
    private function mutations(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value;
        if (! is_array($decoded)) {
            return [];
        }

        $mutations = [];
        foreach ($decoded as $item) {
            if (! is_array($item) || ! is_string($item['effective_date'] ?? null) || ! is_string($item['scope'] ?? null)) {
                continue;
            }
            $mutations[] = new CalendarEventMutation(
                effectiveDate: $item['effective_date'], scope: $item['scope'], cancelled: (bool) ($item['cancelled'] ?? false),
                title: is_string($item['title'] ?? null) ? $item['title'] : null, description: is_string($item['description'] ?? null) ? $item['description'] : null,
                startsAt: is_string($item['starts_at'] ?? null) ? new DateTimeImmutable($item['starts_at']) : null,
                endsAt: is_string($item['ends_at'] ?? null) ? new DateTimeImmutable($item['ends_at']) : null,
                location: is_string($item['location'] ?? null) ? $item['location'] : null, mode: is_string($item['mode'] ?? null) ? $item['mode'] : null,
            );
        }

        return $mutations;
    }

    private function requiredString(mixed $value): string
    {
        if (! is_string($value)) {
            throw new UnexpectedValueException('Calendar contribution persistence returned a non-string timestamp.');
        }

        return $value;
    }
}
