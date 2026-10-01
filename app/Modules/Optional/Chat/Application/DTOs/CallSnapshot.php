<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Calls\CallParticipantState;
use App\Modules\Optional\Chat\Domain\Calls\CallStatus;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationType;

final readonly class CallSnapshot
{
    /**
     * @param  list<array{publicId: string, name: string, state: string, cameraEnabled: bool, microphoneEnabled: bool, screenSharing: bool}>  $participants
     */
    public function __construct(
        public string $publicId,
        public string $conversationPublicId,
        public ConversationType $conversationType,
        public string $conversationLabel,
        public string $startedByUserPublicId,
        public string $startedByName,
        public bool $initialCameraEnabled,
        public CallStatus $status,
        public CallParticipantState $currentUserState,
        public bool $incoming,
        public bool $teamJoinStyle,
        public bool $canRejoin,
        public string $startedAt,
        public ?string $answeredAt,
        public ?string $endedAt,
        public array $participants,
    ) {}
}
