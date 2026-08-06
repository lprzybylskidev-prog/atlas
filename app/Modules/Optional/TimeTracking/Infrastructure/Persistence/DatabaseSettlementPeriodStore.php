<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Persistence;

use App\Modules\Optional\TimeTracking\Application\Contracts\SettlementPeriodStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\SettlementPeriod;
use App\Modules\Optional\TimeTracking\Application\Enums\SettlementPeriodStatus;
use App\Shared\Infrastructure\Database\DatabaseTable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class DatabaseSettlementPeriodStore implements SettlementPeriodStore
{
    public function __construct(private ConnectionInterface $database) {}

    public function startDay(): int
    {
        $day = $this->database->table(DatabaseTable::TIME_TRACKING_SETTLEMENT_SETTINGS)
            ->orderBy('id')
            ->value('period_start_day');

        return is_numeric($day) ? (int) $day : 10;
    }

    public function setStartDay(int $day): void
    {
        if ($day < 1 || $day > 28) {
            throw new InvalidArgumentException('Settlement period start day must be between 1 and 28.');
        }

        $existingId = $this->database->table(DatabaseTable::TIME_TRACKING_SETTLEMENT_SETTINGS)->orderBy('id')->value('id');
        $now = now();

        if (is_numeric($existingId)) {
            $this->database->table(DatabaseTable::TIME_TRACKING_SETTLEMENT_SETTINGS)
                ->where('id', (int) $existingId)
                ->update([
                    'period_start_day' => $day,
                    'updated_at' => $now,
                ]);

            return;
        }

        $this->database->table(DatabaseTable::TIME_TRACKING_SETTLEMENT_SETTINGS)->insert([
            'public_id' => (string) Str::ulid(),
            'period_start_day' => $day,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function periodFor(DateTimeImmutable $date): SettlementPeriod
    {
        [$startsOn, $endsOn] = $this->bounds($date, $this->startDay());
        $row = $this->database->table(DatabaseTable::TIME_TRACKING_SETTLEMENT_PERIODS)
            ->where('starts_on', $startsOn->format('Y-m-d'))
            ->where('ends_on', $endsOn->format('Y-m-d'))
            ->first(['id', 'public_id', 'starts_on', 'ends_on', 'status']);

        if (is_object($row)) {
            return $this->periodFromRow($row);
        }

        $now = now();
        $publicId = (string) Str::ulid();
        $id = $this->database->table(DatabaseTable::TIME_TRACKING_SETTLEMENT_PERIODS)->insertGetId([
            'public_id' => $publicId,
            'starts_on' => $startsOn->format('Y-m-d'),
            'ends_on' => $endsOn->format('Y-m-d'),
            'status' => SettlementPeriodStatus::Open->value,
            'closed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new SettlementPeriod((int) $id, $publicId, $startsOn, $endsOn, SettlementPeriodStatus::Open->value);
    }

    public function closeDuePeriods(DateTimeImmutable $now): int
    {
        $today = $now->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('Y-m-d');

        return $this->database->table(DatabaseTable::TIME_TRACKING_SETTLEMENT_PERIODS)
            ->where('status', SettlementPeriodStatus::Open->value)
            ->where('ends_on', '<', $today)
            ->update([
                'status' => SettlementPeriodStatus::Closed->value,
                'closed_at' => $now,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function bounds(DateTimeImmutable $date, int $startDay): array
    {
        $warsawDate = $date->setTimezone(new DateTimeZone('Europe/Warsaw'));
        $day = (int) $warsawDate->format('j');
        $monthStart = $warsawDate->modify('first day of this month')->setTime(0, 0);
        $startsOn = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('m'), $startDay);

        if ($day < $startDay) {
            $startsOn = $startsOn->modify('-1 month');
        }

        $endsOn = $startsOn->modify('+1 month')->modify('-1 day');

        return [$startsOn, $endsOn];
    }

    private function periodFromRow(object $row): SettlementPeriod
    {
        return new SettlementPeriod(
            id: is_numeric($row->id ?? null) ? (int) $row->id : 0,
            publicId: $this->stringValue($row->public_id ?? null),
            startsOn: new DateTimeImmutable($this->stringValue($row->starts_on ?? null), new DateTimeZone('Europe/Warsaw')),
            endsOn: new DateTimeImmutable($this->stringValue($row->ends_on ?? null), new DateTimeZone('Europe/Warsaw')),
            status: $this->stringValue($row->status ?? null),
        );
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
