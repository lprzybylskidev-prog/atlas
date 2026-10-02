<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class MeetingRtcSession
{
    /** @param list<array{userId:int,microphoneEnabled:bool,microphoneAllowed:bool,cameraEnabled:bool,screenSharing:bool,joinedAt:?string,leftAt:?string,bannedAt:?string}> $participants */
    public function __construct(
        public int $occurrenceId,
        public string $roomName,
        public bool $locked,
        public ?string $startedAt,
        public ?string $endedAt,
        public ?string $emptySince,
        public array $participants,
    ) {}
}
