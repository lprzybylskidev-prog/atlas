<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Exceptions;

use RuntimeException;

final class UserAccountNotFound extends RuntimeException
{
    public static function forPublicId(string $publicId): self
    {
        return new self(sprintf('User account [%s] was not found.', $publicId));
    }
}
