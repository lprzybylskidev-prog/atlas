<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Exceptions;

use App\Shared\Application\Modules\ModuleKey;
use RuntimeException;

final class ModuleDependencyCycle extends RuntimeException
{
    public static function including(ModuleKey $key): self
    {
        return new self(sprintf('Module dependency cycle detected including module [%s].', $key->value));
    }
}
