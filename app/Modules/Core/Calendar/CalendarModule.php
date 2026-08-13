<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar;

use App\Modules\Core\Calendar\Presentation\Providers\CalendarServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class CalendarModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('calendar');
    }

    public function category(): ModuleCategory
    {
        return ModuleCategory::Core;
    }

    public function requiredDependencies(): array
    {
        return [new ModuleKey('identity')];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function serviceProvider(): string
    {
        return CalendarServiceProvider::class;
    }

    public function supportsGlobalActivation(): bool
    {
        return false;
    }

    public function supportsTeamActivation(): bool
    {
        return false;
    }

    public function healthChecks(): array
    {
        return [];
    }
}
