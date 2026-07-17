<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\Contracts;

use App\Modules\Core\Notifications\Application\Public\DTOs\RealtimeEventSummary;

interface RealtimeFeed
{
    /**
     * @return list<RealtimeEventSummary>
     */
    public function visibleEvents(string $userPublicId, ?string $teamPublicId, ?string $afterPublicId, int $limit): array;
}
