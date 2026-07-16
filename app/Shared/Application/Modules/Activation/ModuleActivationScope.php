<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Activation;

enum ModuleActivationScope: string
{
    case Global = 'global';
    case Team = 'team';
}
