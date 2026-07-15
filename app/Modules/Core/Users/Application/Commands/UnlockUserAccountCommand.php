<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Commands;

final readonly class UnlockUserAccountCommand
{
    public function __construct(
        public string $targetPublicId,
        public string $actorPublicId,
        public string $reason,
        public string $source = 'admin',
    ) {}
}
