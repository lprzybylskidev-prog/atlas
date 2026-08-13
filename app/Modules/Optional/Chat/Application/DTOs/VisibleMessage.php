<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use DateTimeImmutable;

final readonly class VisibleMessage
{
    /**
     * @param  list<MessageReaction>  $reactions
     * @param  list<string>  $mentionedUserPublicIds
     */
    public function __construct(
        public string $publicId,
        public string $authorPublicId,
        public ?string $body,
        public ?string $renderedHtml,
        public ?string $replyToMessagePublicId,
        public bool $forwarded,
        public int $version,
        public bool $edited,
        public bool $deletedForViewer,
        public bool $pinned,
        public bool $bookmarked,
        public array $reactions,
        public array $mentionedUserPublicIds,
        public bool $mentionsEveryone,
        public bool $mentionsOnline,
        public DateTimeImmutable $createdAt,
    ) {}
}
