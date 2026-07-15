<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Exceptions;

use InvalidArgumentException;

final class InvalidUserAccountData extends InvalidArgumentException
{
    public static function missingName(): self
    {
        return new self('User account name is required.');
    }

    public static function invalidEmail(): self
    {
        return new self('User account email must be a valid email address.');
    }

    public static function duplicateEmail(string $email): self
    {
        return new self(sprintf('User account email [%s] already exists.', $email));
    }
}
