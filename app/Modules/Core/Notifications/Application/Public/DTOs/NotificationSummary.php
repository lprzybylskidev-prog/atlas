<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\DTOs;

final readonly class NotificationSummary
{
    /**
     * @param  array<string, scalar|null>  $data
     */
    public function __construct(
        public string $publicId,
        public string $type,
        public string $severity,
        public string $title,
        public ?string $body,
        public ?string $deepLinkUrl,
        public ?string $teamPublicId,
        public bool $read,
        public string $createdAt,
        public ?string $readAt,
        public array $data = [],
    ) {}
}
