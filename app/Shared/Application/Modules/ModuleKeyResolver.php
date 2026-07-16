<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

final class ModuleKeyResolver
{
    public function forPermission(string $permission): string
    {
        if ($permission === 'dashboard' || $permission === 'admin.system-status') {
            return 'authorization';
        }

        if (str_starts_with($permission, 'admin.users.')) {
            return 'users';
        }

        if (str_starts_with($permission, 'admin.teams.') || str_starts_with($permission, 'teams.')) {
            return 'teams';
        }

        if (str_starts_with($permission, 'admin.audit.')) {
            return 'audit';
        }

        if (str_starts_with($permission, 'admin.modules.') || str_starts_with($permission, 'modules.')) {
            return 'authorization';
        }

        if (str_starts_with($permission, 'admin.table-views.')) {
            return 'authorization';
        }

        if (str_starts_with($permission, 'admin.authorization.') || str_starts_with($permission, 'authorization.')) {
            return 'authorization';
        }

        if (str_starts_with($permission, 'settings.')) {
            return 'settings';
        }

        return explode('.', $permission)[0] ?: 'authorization';
    }
}
