<?php

declare(strict_types=1);

namespace App\Shared\Application\Outbox\Contracts;

use App\Shared\Application\Outbox\OutboxCleanupResult;
use App\Shared\Application\Outbox\OutboxLagMetrics;

interface OutboxMaintenance
{
    public function cleanup(int $publishedRetentionDays = 30, int $failedRetentionDays = 90): OutboxCleanupResult;

    public function replayFailed(string $eventId): bool;

    public function lagMetrics(): OutboxLagMetrics;
}
