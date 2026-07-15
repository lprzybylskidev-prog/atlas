<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Exceptions;

use InvalidArgumentException;

final class InvalidUserAccountUnlock extends InvalidArgumentException
{
    public static function missingActor(): self
    {
        return new self('User account unlock requires an actor.');
    }

    public static function missingReason(): self
    {
        return new self('User account unlock requires a reason.');
    }
}
