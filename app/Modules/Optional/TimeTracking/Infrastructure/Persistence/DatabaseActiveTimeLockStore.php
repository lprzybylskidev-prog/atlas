<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Persistence;

use App\Modules\Optional\TimeTracking\Application\Contracts\ActiveTimeLockStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\ActiveTimeLock;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseActiveTimeLockStore implements ActiveTimeLockStore
{
    public function __construct(private ConnectionInterface $database) {}

    public function activeForUser(int $userId): ?ActiveTimeLock
    {
        $break = $this->database->table(TimeTrackingDatabaseTable::BREAKS)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first(['public_id']);

        if (is_object($break)) {
            return new ActiveTimeLock('break', $this->stringValue($break->public_id ?? null));
        }

        $otherWork = $this->database->table(TimeTrackingDatabaseTable::OTHER_WORK)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first(['public_id']);

        if (is_object($otherWork)) {
            return new ActiveTimeLock('other_work', $this->stringValue($otherWork->public_id ?? null));
        }

        return null;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
