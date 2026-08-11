<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\DTOs;

use DateTimeInterface;

final readonly class UserNotificationContact
{
    public function __construct(
        public int $internalId,
        public string $publicId,
        public string $email,
        public ?DateTimeInterface $emailVerifiedAt,
    ) {}
}
