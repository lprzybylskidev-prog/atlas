<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Optional\Chat\Presentation\Jobs\RefreshChatSearchProjection;

final class ChatSearchProjectionUpdater
{
    public function refresh(string $sourceType, string $sourcePublicId): void
    {
        RefreshChatSearchProjection::dispatch($sourceType, $sourcePublicId)->onQueue('search')->afterCommit();
    }
}
