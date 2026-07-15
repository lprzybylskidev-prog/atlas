<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Exceptions;

use App\Shared\Application\Modules\ModuleKey;
use RuntimeException;

final class DuplicateModuleKey extends RuntimeException
{
    public static function forKey(ModuleKey $key): self
    {
        return new self(sprintf('Duplicate module key [%s] in deployed module registry.', $key->value));
    }
}
