<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\DTOs;

final readonly class CreateNotification
{
    /**
     * @param  array<string, scalar|null>  $data
     */
    public function __construct(
        public string $type,
        public string $title,
        public ?string $body,
        public string $recipientUserPublicId,
        public ?string $teamPublicId = null,
        public string $severity = 'info',
        public ?string $deepLinkUrl = null,
        public array $data = [],
        public bool $emailRequested = false,
    ) {}
}
