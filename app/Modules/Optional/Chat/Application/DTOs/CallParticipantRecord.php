<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Calls\CallParticipantRole;
use App\Modules\Optional\Chat\Domain\Calls\CallParticipantState;

final readonly class CallParticipantRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $callId,
        public int $userId,
        public CallParticipantRole $role,
        public CallParticipantState $state,
        public bool $cameraEnabled,
        public bool $microphoneEnabled,
        public ?string $joinedAt,
        public ?string $leftAt,
        public ?string $screenShareStartedAt,
    ) {}
}
