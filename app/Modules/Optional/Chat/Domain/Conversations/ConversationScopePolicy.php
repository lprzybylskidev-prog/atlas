<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Domain\Conversations;

final class ConversationScopePolicy
{
    public function allows(ConversationScopeContext $context): bool
    {
        return match ($context->type) {
            ConversationType::Direct, ConversationType::Group => $context->isConversationMember,
            ConversationType::Team => $context->isConversationMember
                && $context->conversationTeamPublicId !== null
                && $context->conversationTeamPublicId === $context->activeTeamPublicId,
            ConversationType::Meeting => $context->isMeetingParticipant,
        };
    }
}
