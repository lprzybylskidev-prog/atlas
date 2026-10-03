<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

final readonly class VisibleMessagePage
{
    /** @param list<VisibleMessage> $messages */
    public function __construct(
        public array $messages,
        public bool $hasOlder,
        public bool $hasNewer,
    ) {}

    public function oldestMessagePublicId(): ?string
    {
        return $this->messages[0]->publicId ?? null;
    }

    public function newestMessagePublicId(): ?string
    {
        if ($this->messages === []) {
            return null;
        }

        return $this->messages[array_key_last($this->messages)]->publicId;
    }
}
