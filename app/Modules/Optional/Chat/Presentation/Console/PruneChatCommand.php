<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Console;

use App\Modules\Optional\Chat\Application\ChatRetention;
use Illuminate\Console\Command;

final class PruneChatCommand extends Command
{
    protected $signature = 'chat:prune';

    protected $description = 'Apply the configured Chat message retention policy.';

    public function handle(ChatRetention $retention): int
    {
        $result = $retention->cleanup();
        $this->info($result['disabled'] ? 'Chat retention is disabled.' : sprintf('Chat messages: %d removed, %d file failures.', $result['removed'], $result['failed']));

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
