<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class ChatRetentionCandidate
{
    /**
     * @param  list<array{publicId:string,filePublicId:string}>  $attachments
     */
    public function __construct(
        public int $id,
        public string $publicId,
        public array $attachments,
    ) {}
}
