<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat;

use App\Modules\Optional\Chat\Presentation\Providers\ChatServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class ChatModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('chat');
    }

    public function category(): ModuleCategory
    {
        return ModuleCategory::Optional;
    }

    public function requiredDependencies(): array
    {
        return [
            new ModuleKey('identity'),
            new ModuleKey('files'),
            new ModuleKey('calendar'),
            new ModuleKey('teams'),
            new ModuleKey('audit'),
        ];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function serviceProvider(): string
    {
        return ChatServiceProvider::class;
    }

    public function supportsGlobalActivation(): bool
    {
        return true;
    }

    public function supportsTeamActivation(): bool
    {
        return false;
    }

    public function healthChecks(): array
    {
        return ['reverb'];
    }
}
