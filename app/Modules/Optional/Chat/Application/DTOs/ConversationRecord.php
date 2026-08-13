<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\DTOs;

use App\Modules\Optional\Chat\Domain\Conversations\ConversationType;

final readonly class ConversationRecord
{
    public function __construct(
        public int $id,
        public string $publicId,
        public ConversationType $type,
        public ?string $name,
        public ?string $teamPublicId,
        public ?string $meetingOwnerKey,
        public bool $systemOwned,
        public bool $closed,
        public int $version,
    ) {}
}
