<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Conversations;

final readonly class ConversationScopeContext
{
    public function __construct(
        public ConversationType $type,
        public bool $isConversationMember,
        public ?string $conversationTeamPublicId = null,
        public ?string $activeTeamPublicId = null,
        public bool $isMeetingParticipant = false,
    ) {}
}
