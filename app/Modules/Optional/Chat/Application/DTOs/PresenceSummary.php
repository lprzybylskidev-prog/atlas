<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Realtime\ManualStatus;
use DateTimeImmutable;

final readonly class PresenceSummary
{
    public function __construct(
        public string $userPublicId,
        public string $name,
        public bool $online,
        public ?DateTimeImmutable $lastSeenAt,
        public ManualStatus $manualStatus,
        public ?string $customText,
        public ?string $customEmoji,
    ) {}
}
