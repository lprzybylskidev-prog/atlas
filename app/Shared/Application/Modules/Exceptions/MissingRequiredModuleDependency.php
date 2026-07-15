<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Exceptions;

use App\Shared\Application\Modules\ModuleKey;
use RuntimeException;

final class MissingRequiredModuleDependency extends RuntimeException
{
    public static function forDependency(ModuleKey $module, ModuleKey $dependency): self
    {
        return new self(sprintf(
            'Module [%s] requires missing deployed dependency [%s].',
            $module->value,
            $dependency->value,
        ));
    }

    public static function forMissingModule(ModuleKey $module): self
    {
        return new self(sprintf('Module [%s] is not deployed.', $module->value));
    }
}
