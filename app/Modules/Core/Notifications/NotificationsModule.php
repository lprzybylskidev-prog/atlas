<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications;

use App\Modules\Core\Notifications\Presentation\Providers\NotificationsServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class NotificationsModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('notifications');
    }

    public function category(): ModuleCategory
    {
        return ModuleCategory::Core;
    }

    public function requiredDependencies(): array
    {
        return [
            new ModuleKey('identity'),
            new ModuleKey('authorization'),
            new ModuleKey('teams'),
            new ModuleKey('settings'),
        ];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function serviceProvider(): string
    {
        return NotificationsServiceProvider::class;
    }

    public function supportsGlobalActivation(): bool
    {
        return false;
    }

    public function supportsTeamActivation(): bool
    {
        return false;
    }

    public function integrations(): array
    {
        return [];
    }

    public function healthChecks(): array
    {
        return [];
    }

    public function frontendEntrypoints(): array
    {
        return [];
    }
}
