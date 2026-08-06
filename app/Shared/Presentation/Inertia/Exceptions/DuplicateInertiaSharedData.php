<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Inertia\Exceptions;

use RuntimeException;

final class DuplicateInertiaSharedData extends RuntimeException
{
    public static function forPath(string $path, string $firstOwner, string $secondOwner): self
    {
        return new self(sprintf(
            'Inertia shared data path [%s] is already owned by [%s] and cannot also be owned by [%s].',
            $path,
            $firstOwner,
            $secondOwner,
        ));
    }
}
