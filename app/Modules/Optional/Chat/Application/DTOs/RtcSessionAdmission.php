<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Rtc\RtcSessionMode;
use InvalidArgumentException;

final readonly class RtcSessionAdmission
{
    public function __construct(
        public string $sessionPublicId,
        public string $roomName,
        public RtcSessionMode $mode,
        public string $userPublicId,
        public string $participantName,
        public bool $canPublish = true,
        public bool $canSubscribe = true,
    ) {
        foreach ([$sessionPublicId, $roomName, $userPublicId, $participantName] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('RTC admission values must be non-empty strings.');
            }
        }

        if (preg_match('/\A[a-z0-9][a-z0-9_-]{2,127}\z/', $roomName) !== 1) {
            throw new InvalidArgumentException('RTC room names must use the canonical lowercase Atlas format.');
        }
    }
}
