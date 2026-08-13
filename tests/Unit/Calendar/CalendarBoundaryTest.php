<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Modules\Core\Calendar\Application\Public\DTOs\CalendarEventContribution;
use App\Modules\Core\Calendar\Application\Public\DTOs\FreeBusyQuery;
use App\Modules\Core\Calendar\Domain\Events\PersonalCalendarEvent;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalendarBoundaryTest extends TestCase
{
    public function test_personal_events_have_no_participant_or_invitation_state(): void
    {
        $properties = array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            (new \ReflectionClass(PersonalCalendarEvent::class))->getProperties(),
        );

        self::assertNotContains('participants', $properties);
        self::assertNotContains('participantUserPublicIds', $properties);
        self::assertNotContains('invitations', $properties);
    }

    public function test_module_event_contribution_has_an_owner_and_authorized_participants(): void
    {
        $event = new CalendarEventContribution(
            sourceModule: 'chat',
            sourceEventPublicId: '01K00000000000000000000001',
            title: 'Collection review',
            startsAt: new DateTimeImmutable('2026-08-13 10:00:00 Europe/Warsaw'),
            endsAt: new DateTimeImmutable('2026-08-13 11:00:00 Europe/Warsaw'),
            allDay: false,
            participantUserPublicIds: ['01K00000000000000000000002'],
        );

        self::assertSame('chat', $event->sourceModule);
        self::assertSame(['01K00000000000000000000002'], $event->participantUserPublicIds);
    }

    #[DataProvider('invalidWindowProvider')]
    public function test_calendar_boundaries_reject_invalid_time_windows(string $start, string $end): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FreeBusyQuery(
            ['01K00000000000000000000002'],
            new DateTimeImmutable($start),
            new DateTimeImmutable($end),
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidWindowProvider(): iterable
    {
        yield 'equal' => ['2026-08-13 10:00:00', '2026-08-13 10:00:00'];
        yield 'reversed' => ['2026-08-13 11:00:00', '2026-08-13 10:00:00'];
    }
}
