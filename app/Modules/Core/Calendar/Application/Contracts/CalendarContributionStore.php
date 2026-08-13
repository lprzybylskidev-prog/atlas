<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Contracts;

use App\Modules\Core\Calendar\Application\Public\DTOs\CalendarEventContribution;
use App\Modules\Core\Calendar\Application\Public\DTOs\FreeBusyWindow;
use DateTimeImmutable;

interface CalendarContributionStore
{
    public function upsert(CalendarEventContribution $event): void;

    public function remove(string $sourceModule, string $sourceEventPublicId): void;

    /**
     * @param  list<string>  $userPublicIds
     * @return list<FreeBusyWindow>
     */
    public function busyWindows(array $userPublicIds, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array;
}
