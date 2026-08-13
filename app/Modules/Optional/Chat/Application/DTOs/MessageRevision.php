<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use DateTimeImmutable;

final readonly class MessageRevision
{
    public function __construct(
        public int $version,
        public string $body,
        public string $renderedHtml,
        public DateTimeImmutable $createdAt,
    ) {}
}
