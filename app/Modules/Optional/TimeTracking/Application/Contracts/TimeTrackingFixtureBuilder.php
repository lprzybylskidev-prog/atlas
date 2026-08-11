<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Contracts;

interface TimeTrackingFixtureBuilder
{
    public function available(): bool;

    public function seed(): void;
}
