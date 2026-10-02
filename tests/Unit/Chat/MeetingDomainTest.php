<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use App\Modules\Optional\Chat\Application\DTOs\MeetingInput;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingMode;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecurrence;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MeetingDomainTest extends TestCase
{
    #[DataProvider('validModes')]
    public function test_mode_defines_location_and_rtc_capability(MeetingMode $mode, ?string $location, bool $rtc): void
    {
        $input = new MeetingInput('Planning', null, new DateTimeImmutable('2026-10-02 10:00 Europe/Warsaw'), new DateTimeImmutable('2026-10-02 11:00 Europe/Warsaw'), $mode, $location, null, []);
        self::assertSame($rtc, $input->mode->hasRtc());
    }

    public function test_in_person_and_hybrid_require_a_physical_location(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MeetingInput('Planning', null, new DateTimeImmutable('2026-10-02 10:00 Europe/Warsaw'), new DateTimeImmutable('2026-10-02 11:00 Europe/Warsaw'), MeetingMode::InPerson, null, null, []);
    }

    public function test_recurrence_supports_selected_weekdays_and_one_end_condition(): void
    {
        $rule = new MeetingRecurrence('weekly', [1, 3, 5], occurrenceCount: 12);
        self::assertSame([1, 3, 5], $rule->weekdays);
        self::assertSame(12, $rule->occurrenceCount);
    }

    /** @return iterable<string,array{MeetingMode,?string,bool}> */
    public static function validModes(): iterable
    {
        yield 'online' => [MeetingMode::Online, null, true];
        yield 'in person' => [MeetingMode::InPerson, 'Room 3', false];
        yield 'hybrid' => [MeetingMode::Hybrid, 'Floor 2', true];
    }
}
