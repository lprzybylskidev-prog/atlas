<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class ConversationContent
{
    /**
     * @param  list<MessageAttachment>  $media
     * @param  list<MessageAttachment>  $files
     * @param  list<string>  $links
     */
    public function __construct(
        public array $media,
        public array $files,
        public array $links,
    ) {}
}
