<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Contracts;

use App\Modules\Optional\TimeTracking\Application\DTOs\ActiveWorkSession;
use App\Modules\Optional\TimeTracking\Application\Enums\WorkSessionClosureReason;
use DateTimeImmutable;

interface WorkSessionStore
{
    public function ensureActive(int $userId, int $teamId, string $laravelSessionId, DateTimeImmutable $startedAt): ActiveWorkSession;

    public function closeActiveForUser(int $userId, WorkSessionClosureReason $reason, DateTimeImmutable $endedAt): void;

    public function closeSession(int $sessionId, WorkSessionClosureReason $reason, DateTimeImmutable $endedAt): bool;

    public function recordModuleContext(int $workSessionId, string $moduleKey, string $contextKey, DateTimeImmutable $startedAt): void;
}
