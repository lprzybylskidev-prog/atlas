<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use DateTimeImmutable;

final readonly class RtcParticipantAccess
{
    public function __construct(
        public string $serverUrl,
        public string $roomName,
        public string $participantIdentity,
        public string $token,
        public DateTimeImmutable $expiresAt,
    ) {}
}
