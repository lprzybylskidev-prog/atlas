<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Public\Contracts;

use App\Modules\Core\Calendar\Application\Public\DTOs\CalendarEventContribution;

interface CalendarEventPublisher
{
    public function upsert(CalendarEventContribution $event): void;

    public function remove(string $sourceModule, string $sourceEventPublicId): void;
}
