<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

use DateTimeImmutable;

final readonly class ActiveOtherWorkSession
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $userId,
        public int $teamId,
        public int $workSessionId,
        public ?string $categoryKey,
        public DateTimeImmutable $startedAt,
    ) {}
}
