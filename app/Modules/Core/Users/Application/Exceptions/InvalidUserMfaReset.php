<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Exceptions;

use InvalidArgumentException;

final class InvalidUserMfaReset extends InvalidArgumentException
{
    public static function missingActor(): self
    {
        return new self('User MFA reset requires an actor.');
    }

    public static function missingReason(): self
    {
        return new self('User MFA reset requires a reason.');
    }
}
