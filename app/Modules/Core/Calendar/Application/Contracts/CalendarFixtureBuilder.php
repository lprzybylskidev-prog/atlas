<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Contracts;

interface CalendarFixtureBuilder
{
    public function provideVisibilityEvents(int $userId): void;
}
