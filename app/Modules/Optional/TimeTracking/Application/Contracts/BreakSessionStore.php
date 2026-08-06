<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Contracts;

use App\Modules\Optional\TimeTracking\Application\DTOs\ActiveBreakSession;
use App\Modules\Optional\TimeTracking\Application\Enums\BreakClosureReason;
use DateTimeImmutable;

interface BreakSessionStore
{
    public function startForActiveWorkSession(int $userId, DateTimeImmutable $startedAt): ActiveBreakSession;

    public function closeActiveForUser(
        int $userId,
        BreakClosureReason $reason,
        DateTimeImmutable $endedAt,
        bool $requiresManagerReview,
    ): void;

    public function closeExpired(DateTimeImmutable $now): int;

    public function recordDueReminders(DateTimeImmutable $now): int;
}
