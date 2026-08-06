<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Contracts;

use App\Modules\Optional\TimeTracking\Application\DTOs\ActiveOtherWorkSession;
use App\Modules\Optional\TimeTracking\Application\Enums\OtherWorkApprovalStatus;
use App\Modules\Optional\TimeTracking\Application\Enums\OtherWorkClosureReason;
use DateTimeImmutable;

interface OtherWorkSessionStore
{
    public function startForActiveWorkSession(
        int $userId,
        ?string $categoryKey,
        string $description,
        DateTimeImmutable $startedAt,
    ): ActiveOtherWorkSession;

    public function closeActiveForUser(int $userId, OtherWorkClosureReason $reason, DateTimeImmutable $endedAt, ?string $endNote): void;

    public function moveActiveToUnderReview(int $userId, string $reason): void;

    public function decidePending(int $otherWorkId, OtherWorkApprovalStatus $status): bool;
}
