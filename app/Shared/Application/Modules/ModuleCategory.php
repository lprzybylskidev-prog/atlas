<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

enum ModuleCategory: string
{
    case Core = 'core';
    case Optional = 'optional';
    case Application = 'application';
}
