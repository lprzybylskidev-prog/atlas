<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Contracts;

use App\Modules\Core\Calendar\Application\DTOs\CalendarPreference;

interface CalendarPreferenceStore
{
    public function forUser(int $userId): CalendarPreference;

    public function save(int $userId, CalendarPreference $preference): void;
}
