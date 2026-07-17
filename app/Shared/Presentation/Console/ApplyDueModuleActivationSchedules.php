<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Console;

use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;
use Illuminate\Console\Command;

final class ApplyDueModuleActivationSchedules extends Command
{
    protected $signature = 'modules:activation:apply-due';

    protected $description = 'Apply due scheduled module activation changes.';

    public function handle(ModuleActivationService $activation, OperationalModuleGuard $modules): int
    {
        $modules->ensureAllowed('authorization');

        $applied = $activation->applyDueSchedules();
        $this->info(sprintf('Applied %d due module activation schedule(s).', $applied));

        return self::SUCCESS;
    }
}
