<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Rtc;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\Chat\Application\Contracts\CallStore;
use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\Contracts\RtcSessionAccessAuthorizer;
use App\Modules\Optional\Chat\Application\ConversationManager;
use App\Modules\Optional\Chat\Application\DTOs\RtcSessionAdmission;
use App\Modules\Optional\Chat\Application\Exceptions\RtcAccessDenied;
use App\Modules\Optional\Chat\Domain\Calls\CallParticipantState;
use App\Modules\Optional\Chat\Domain\Rtc\RtcSessionMode;

final readonly class DatabaseCallSessionAccessAuthorizer implements RtcSessionAccessAuthorizer
{
    public function __construct(
        private CallStore $calls,
        private ConversationStore $conversationStore,
        private ConversationManager $conversations,
        private UserLookup $users,
    ) {}

    public function authorize(string $sessionPublicId, string $userPublicId, string $activeTeamPublicId): RtcSessionAdmission
    {
        $call = $this->calls->findByPublicId($sessionPublicId);
        $userId = $this->users->internalIdForPublicId($userPublicId);

        if ($call === null || $userId === null || ! $call->status->acceptsParticipants()) {
            throw RtcAccessDenied::sessionUnavailable();
        }

        $participant = $this->calls->participant($call->id, $userId);

        if ($participant?->state !== CallParticipantState::Joined) {
            throw RtcAccessDenied::sessionUnavailable();
        }

        $conversation = $this->conversationStore->findById($call->conversationId);

        if ($conversation === null || ! $this->conversations->canAccess($userPublicId, $activeTeamPublicId, $conversation->publicId)) {
            throw RtcAccessDenied::sessionUnavailable();
        }

        $summary = $this->users->displaySummariesForPublicIds([$userPublicId])[$userPublicId] ?? null;

        return new RtcSessionAdmission(
            sessionPublicId: $call->publicId,
            roomName: $call->roomName,
            mode: RtcSessionMode::AdHocCall,
            userPublicId: $userPublicId,
            participantName: $summary !== null ? $summary->name : 'Atlas user',
        );
    }
}
