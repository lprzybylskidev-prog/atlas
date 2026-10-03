<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class MessageRecordPage
{
    /** @param list<MessageRecord> $messages */
    public function __construct(
        public array $messages,
        public bool $hasOlder,
        public bool $hasNewer,
    ) {}
}
