<?php

declare(strict_types=1);

namespace App\Shared\Application\Queries;

use InvalidArgumentException;

final readonly class PageCursor
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Page cursor must be a non-empty string.');
        }
    }
}
