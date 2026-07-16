<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Sessions;

final readonly class UserSessionMetadata
{
    public function __construct(
        public string $sessionId,
        public int $userId,
        public string $userPublicId,
        public string $userName,
        public string $userEmail,
        public string $createdAt,
        public string $lastActivityAt,
        public string $ipAddress,
        public string $ipLocation,
        public string $userAgent,
        public string $browser,
        public string $device,
        public ?string $activeTeamPublicId,
        public ?string $activeTeamName,
    ) {}
}
