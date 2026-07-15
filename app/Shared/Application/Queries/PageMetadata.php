<?php

declare(strict_types=1);

namespace App\Shared\Application\Queries;

use InvalidArgumentException;

final readonly class PageMetadata
{
    public function __construct(
        public int $page,
        public int $perPage,
        public ?int $totalItems = null,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('Page number must be greater than zero.');
        }

        if ($perPage < 1) {
            throw new InvalidArgumentException('Per-page value must be greater than zero.');
        }

        if ($totalItems !== null && $totalItems < 0) {
            throw new InvalidArgumentException('Total item count cannot be negative.');
        }
    }
}
