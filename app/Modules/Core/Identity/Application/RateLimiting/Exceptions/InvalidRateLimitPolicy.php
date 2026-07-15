<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\RateLimiting\Exceptions;

use RuntimeException;

final class InvalidRateLimitPolicy extends RuntimeException
{
    public static function missing(string $name): self
    {
        return new self(sprintf('Rate-limit policy [%s] is not configured.', $name));
    }

    public static function invalid(string $name, string $reason): self
    {
        return new self(sprintf('Rate-limit policy [%s] is invalid: %s', $name, $reason));
    }
}
