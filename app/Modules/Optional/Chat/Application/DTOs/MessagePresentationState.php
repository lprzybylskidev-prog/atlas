<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class MessagePresentationState
{
    /**
     * @param  list<MessageReaction>  $reactions
     * @param  list<string>  $mentionedUserPublicIds
     */
    public function __construct(
        public bool $hidden,
        public bool $replyHidden,
        public bool $pinned,
        public bool $bookmarked,
        public array $reactions,
        public array $mentionedUserPublicIds,
        public bool $mentionsEveryone,
        public bool $mentionsOnline,
    ) {}
}
