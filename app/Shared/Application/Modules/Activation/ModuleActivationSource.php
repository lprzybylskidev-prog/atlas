<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Activation;

enum ModuleActivationSource: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case System = 'system';
}
