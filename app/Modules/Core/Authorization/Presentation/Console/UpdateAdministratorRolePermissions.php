<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Console;

use App\Modules\Core\Authorization\Application\Roles\AdministratorRoleUpdater;
use Illuminate\Console\Command;

final class UpdateAdministratorRolePermissions extends Command
{
    protected $signature = 'authorization:update-administrator-role
        {--apply : Apply the missing permission additions}
        {--reason= : Required reason when applying changes}
        {--actor= : Optional actor public ID for audit}';

    protected $description = 'Show or apply the explicit administrator role permission diff.';

    public function handle(AdministratorRoleUpdater $updater): int
    {
        $diff = $updater->diff();

        if (! $diff->hasMissingPermissions()) {
            $this->info('Administrator role is already aligned with the registered permission catalog.');

            return self::SUCCESS;
        }

        $this->warn('Administrator role is missing permissions:');

        foreach ($diff->missingPermissionNames as $permission) {
            $this->line('- '.$permission);
        }

        if (! $this->option('apply')) {
            $this->line('Run again with --apply and --reason to apply these additions.');

            return self::SUCCESS;
        }

        $reason = $this->option('reason');

        if (! is_string($reason) || trim($reason) === '') {
            $this->error('A non-empty --reason is required to update the administrator role.');

            return self::FAILURE;
        }

        $actor = $this->option('actor');
        $actor = is_string($actor) && trim($actor) !== '' ? trim($actor) : null;

        $updater->apply($actor, trim($reason));
        $this->info('Administrator role permissions were updated.');

        return self::SUCCESS;
    }
}
