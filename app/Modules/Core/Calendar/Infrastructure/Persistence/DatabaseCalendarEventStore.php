<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Infrastructure\Persistence;

use App\Modules\Core\Calendar\Application\Contracts\CalendarEventStore;
use App\Modules\Core\Calendar\Application\DTOs\CalendarEventInput;
use App\Modules\Core\Calendar\Domain\Events\CalendarAvailability;
use App\Modules\Core\Calendar\Domain\Events\PersonalCalendarEvent;
use App\Modules\Core\Calendar\Domain\Events\RecurrenceFrequency;
use App\Modules\Core\Calendar\Domain\Events\RecurrenceRule;
use App\Modules\Core\Calendar\Infrastructure\Persistence\TableNames\CalendarDatabaseTable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use stdClass;
use UnexpectedValueException;

final readonly class DatabaseCalendarEventStore implements CalendarEventStore
{
    private const TIMEZONE = 'Europe/Warsaw';

    public function __construct(private ConnectionInterface $database) {}

    public function forOwnerBefore(int $ownerUserId, DateTimeImmutable $rangeEndsAt): array
    {
        return $this->eventsForQuery(
            $this->database->table(CalendarDatabaseTable::PERSONAL_EVENTS)
                ->where('user_id', $ownerUserId)
                ->where('starts_at', '<', $rangeEndsAt->format(DATE_ATOM)),
        );
    }

    public function forOwnersBefore(array $ownerUserIds, DateTimeImmutable $rangeEndsAt): array
    {
        if ($ownerUserIds === []) {
            return [];
        }

        return $this->eventsForQuery(
            $this->database->table(CalendarDatabaseTable::PERSONAL_EVENTS)
                ->whereIn('user_id', $ownerUserIds)
                ->where('starts_at', '<', $rangeEndsAt->format(DATE_ATOM)),
        );
    }

    public function findOwned(string $eventPublicId, int $ownerUserId): ?PersonalCalendarEvent
    {
        $row = $this->database->table(CalendarDatabaseTable::PERSONAL_EVENTS)
            ->where('public_id', $eventPublicId)
            ->where('user_id', $ownerUserId)
            ->first();

        return is_object($row) ? $this->mapEvent($row) : null;
    }

    public function create(string $publicId, int $ownerUserId, CalendarEventInput $input): void
    {
        $eventId = $this->database->table(CalendarDatabaseTable::PERSONAL_EVENTS)->insertGetId([
            'public_id' => $publicId,
            'user_id' => $ownerUserId,
            ...$this->eventValues($input),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->replaceReminders((int) $eventId, $input->reminderMinutes);
    }

    public function update(string $eventPublicId, int $ownerUserId, int $expectedVersion, CalendarEventInput $input): bool
    {
        $updated = $this->database->table(CalendarDatabaseTable::PERSONAL_EVENTS)
            ->where('public_id', $eventPublicId)
            ->where('user_id', $ownerUserId)
            ->where('version', $expectedVersion)
            ->update([
                ...$this->eventValues($input),
                'version' => $expectedVersion + 1,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            return false;
        }

        $eventId = $this->eventInternalId($eventPublicId, $ownerUserId);
        if ($eventId !== null) {
            $this->replaceReminders($eventId, $input->reminderMinutes);
        }

        return true;
    }

    public function delete(string $eventPublicId, int $ownerUserId, int $expectedVersion): bool
    {
        return $this->database->table(CalendarDatabaseTable::PERSONAL_EVENTS)
            ->where('public_id', $eventPublicId)
            ->where('user_id', $ownerUserId)
            ->where('version', $expectedVersion)
            ->delete() === 1;
    }

    public function upsertOccurrenceOverride(
        string $eventPublicId,
        int $ownerUserId,
        string $occurrenceDate,
        ?CalendarEventInput $input,
    ): void {
        $eventId = $this->eventInternalId($eventPublicId, $ownerUserId);

        if ($eventId === null) {
            return;
        }

        $this->database->table(CalendarDatabaseTable::RECURRENCE_EXCEPTIONS)->updateOrInsert(
            ['event_id' => $eventId, 'occurrence_date' => $occurrenceDate],
            [
                'cancelled' => $input === null,
                'override_payload' => $input === null ? null : json_encode($this->inputPayload($input), JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function occurrenceOverrides(array $eventPublicIds): array
    {
        if ($eventPublicIds === []) {
            return [];
        }

        $rows = $this->database->table(CalendarDatabaseTable::RECURRENCE_EXCEPTIONS.' as exceptions')
            ->join(CalendarDatabaseTable::PERSONAL_EVENTS.' as events', 'events.id', '=', 'exceptions.event_id')
            ->whereIn('events.public_id', $eventPublicIds)
            ->get(['events.public_id', 'exceptions.occurrence_date', 'exceptions.cancelled', 'exceptions.override_payload']);
        $overrides = [];

        foreach ($rows as $row) {
            $eventPublicId = $this->requiredString($row->public_id);
            $date = substr($this->requiredString($row->occurrence_date), 0, 10);
            $overrides[$eventPublicId][$date] = (bool) $row->cancelled
                ? null
                : $this->inputFromPayload($this->decodeObject($row->override_payload));
        }

        return $overrides;
    }

    public function splitSeries(
        PersonalCalendarEvent $original,
        string $occurrenceDate,
        string $newPublicId,
        CalendarEventInput $futureInput,
    ): bool {
        $previousDay = (new DateTimeImmutable($occurrenceDate, new DateTimeZone(self::TIMEZONE)))->modify('-1 day');
        $updated = $this->database->table(CalendarDatabaseTable::PERSONAL_EVENTS)
            ->where('public_id', $original->publicId)
            ->where('user_id', $original->ownerUserId)
            ->where('version', $original->version)
            ->update([
                'recurrence_ends_on' => $previousDay->format('Y-m-d'),
                'recurrence_count' => null,
                'version' => $original->version + 1,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            return false;
        }

        $this->create($newPublicId, $original->ownerUserId, $futureInput);

        return true;
    }

    /** @return list<PersonalCalendarEvent> */
    private function eventsForQuery(Builder $query): array
    {
        $rows = $query->orderBy('starts_at')->get();

        return array_values(array_map(fn (stdClass $row): PersonalCalendarEvent => $this->mapEvent($row), $rows->all()));
    }

    private function mapEvent(stdClass $row): PersonalCalendarEvent
    {
        $eventId = $this->requiredInt($row->id);
        $reminders = array_values($this->database->table(CalendarDatabaseTable::REMINDERS)
            ->where('event_id', $eventId)
            ->orderBy('minutes_before')
            ->pluck('minutes_before')
            ->map(fn (mixed $minutes): int => $this->requiredInt($minutes))
            ->all());
        $frequency = is_string($row->recurrence_frequency) && $row->recurrence_frequency !== ''
            ? RecurrenceFrequency::tryFrom($row->recurrence_frequency)
            : null;

        return new PersonalCalendarEvent(
            publicId: $this->requiredString($row->public_id),
            ownerUserId: $this->requiredInt($row->user_id),
            title: $this->requiredString($row->title),
            description: $this->nullableString($row->description),
            startsAt: new DateTimeImmutable($this->requiredString($row->starts_at)),
            endsAt: new DateTimeImmutable($this->requiredString($row->ends_at)),
            allDay: (bool) $row->all_day,
            location: $this->nullableString($row->location),
            availability: CalendarAvailability::from($this->requiredString($row->availability)),
            recurrence: $frequency === null ? null : new RecurrenceRule(
                frequency: $frequency,
                interval: $this->requiredInt($row->recurrence_interval),
                weekdays: $this->integerList($row->recurrence_weekdays),
                endsOn: $this->nullableDate($row->recurrence_ends_on),
                occurrenceCount: is_numeric($row->recurrence_count) ? (int) $row->recurrence_count : null,
            ),
            reminderMinutes: $reminders,
            version: $this->requiredInt($row->version),
        );
    }

    /** @return array<string, mixed> */
    private function eventValues(CalendarEventInput $input): array
    {
        return [
            'title' => $input->title,
            'description' => $input->description,
            'starts_at' => $input->startsAt->format(DATE_ATOM),
            'ends_at' => $input->endsAt->format(DATE_ATOM),
            'all_day' => $input->allDay,
            'location' => $input->location,
            'availability' => $input->availability->value,
            'recurrence_frequency' => $input->recurrence?->frequency->value,
            'recurrence_interval' => $input->recurrence === null ? 1 : $input->recurrence->interval,
            'recurrence_weekdays' => $input->recurrence === null ? null : json_encode($input->recurrence->weekdays, JSON_THROW_ON_ERROR),
            'recurrence_ends_on' => $input->recurrence?->endsOn?->format('Y-m-d'),
            'recurrence_count' => $input->recurrence?->occurrenceCount,
        ];
    }

    /** @param list<int> $minutes */
    private function replaceReminders(int $eventId, array $minutes): void
    {
        $this->database->table(CalendarDatabaseTable::REMINDERS)->where('event_id', $eventId)->delete();

        foreach ($minutes as $value) {
            $this->database->table(CalendarDatabaseTable::REMINDERS)->insert([
                'event_id' => $eventId,
                'minutes_before' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function eventInternalId(string $publicId, int $ownerUserId): ?int
    {
        $value = $this->database->table(CalendarDatabaseTable::PERSONAL_EVENTS)
            ->where('public_id', $publicId)
            ->where('user_id', $ownerUserId)
            ->value('id');

        return is_numeric($value) ? (int) $value : null;
    }

    /** @return array<string, mixed> */
    private function inputPayload(CalendarEventInput $input): array
    {
        return [
            'title' => $input->title,
            'description' => $input->description,
            'starts_at' => $input->startsAt->format(DATE_ATOM),
            'ends_at' => $input->endsAt->format(DATE_ATOM),
            'all_day' => $input->allDay,
            'location' => $input->location,
            'availability' => $input->availability->value,
            'reminder_minutes' => $input->reminderMinutes,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function inputFromPayload(array $payload): CalendarEventInput
    {
        return new CalendarEventInput(
            title: $this->requiredString($payload['title'] ?? ''),
            description: $this->nullableString($payload['description'] ?? null),
            startsAt: new DateTimeImmutable($this->requiredString($payload['starts_at'] ?? 'now')),
            endsAt: new DateTimeImmutable($this->requiredString($payload['ends_at'] ?? 'now')),
            allDay: (bool) ($payload['all_day'] ?? false),
            location: $this->nullableString($payload['location'] ?? null),
            availability: CalendarAvailability::from($this->requiredString($payload['availability'] ?? 'busy')),
            recurrence: null,
            reminderMinutes: $this->integerList($payload['reminder_minutes'] ?? []),
        );
    }

    /** @return array<array-key, mixed> */
    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private function decodeObject(mixed $value): array
    {
        $decoded = $this->decodeArray($value);
        $result = [];
        foreach ($decoded as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /** @return list<int> */
    private function integerList(mixed $value): array
    {
        $integers = [];
        foreach ($this->decodeArray($value) as $item) {
            $integers[] = $this->requiredInt($item);
        }

        return $integers;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function nullableDate(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== ''
            ? new DateTimeImmutable(substr($value, 0, 10), new DateTimeZone(self::TIMEZONE))
            : null;
    }

    private function requiredString(mixed $value): string
    {
        if (! is_string($value)) {
            throw new UnexpectedValueException('Calendar persistence returned a non-string value.');
        }

        return $value;
    }

    private function requiredInt(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new UnexpectedValueException('Calendar persistence returned a non-integer value.');
        }

        return (int) $value;
    }
}
