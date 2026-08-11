<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Exceptions;

use App\Shared\Application\Modules\ModuleKey;
use RuntimeException;

final class InvalidModuleDefinition extends RuntimeException
{
    public static function forReason(ModuleKey $module, string $reason): self
    {
        return new self(sprintf('Invalid module definition for [%s]: %s', $module->value, $reason));
    }
}
