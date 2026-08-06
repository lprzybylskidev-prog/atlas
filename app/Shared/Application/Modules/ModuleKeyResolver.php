<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

final class ModuleKeyResolver
{
    public function forPermission(string $permission): string
    {
        if ($permission === 'login' || str_starts_with($permission, 'password.')) {
            return 'identity';
        }

        if (str_starts_with($permission, 'admin-mode.') || str_starts_with($permission, 'impersonation.')) {
            return 'identity';
        }

        if ($permission === 'admin.system-status.release' || $permission === 'admin.system-status.readiness') {
            return 'health';
        }

        if ($permission === 'dashboard' || $permission === 'admin.system-status' || str_starts_with($permission, 'admin.system-status.')) {
            return 'authorization';
        }

        if ($permission === 'users.work-time' || str_starts_with($permission, 'users.work-time.')) {
            return 'time_tracking';
        }

        if (str_starts_with($permission, 'users.notifications.')) {
            return 'notifications';
        }

        if (str_starts_with($permission, 'admin.users.') || str_starts_with($permission, 'users.')) {
            return 'users';
        }

        if (str_starts_with($permission, 'admin.teams.') || str_starts_with($permission, 'admin.managers.') || str_starts_with($permission, 'teams.')) {
            return 'teams';
        }

        if (str_starts_with($permission, 'admin.audit.')) {
            return 'audit';
        }

        if (str_starts_with($permission, 'admin.rate-limits.')) {
            return 'identity';
        }

        if (str_starts_with($permission, 'admin.logs.')) {
            return 'authorization';
        }

        if (str_starts_with($permission, 'admin.queues.')) {
            return 'authorization';
        }

        if (str_starts_with($permission, 'admin.pulse.') || str_starts_with($permission, 'admin.telescope.')) {
            return 'authorization';
        }

        if (str_starts_with($permission, 'admin.files.') || str_starts_with($permission, 'files.')) {
            return 'files';
        }

        if (str_starts_with($permission, 'admin.privacy-retention.') || str_starts_with($permission, 'privacy.')) {
            return 'privacy';
        }

        if (str_starts_with($permission, 'admin.feature-flags.') || str_starts_with($permission, 'feature-flags.')) {
            return 'feature_flags';
        }

        if (str_starts_with($permission, 'admin.integrations.') || str_starts_with($permission, 'integrations.')) {
            return 'integrations';
        }

        if (str_starts_with($permission, 'admin.managed-processes.') || str_starts_with($permission, 'managed-processes.')) {
            return 'managed_processes';
        }

        if (str_starts_with($permission, 'imports.')) {
            return 'imports';
        }

        if (str_starts_with($permission, 'admin.search.') || str_starts_with($permission, 'search.')) {
            return 'search';
        }

        if (str_starts_with($permission, 'admin.exports.') || str_starts_with($permission, 'exports.')) {
            return 'exports';
        }

        if (str_starts_with($permission, 'admin.reports.') || str_starts_with($permission, 'reports.')) {
            return 'reports';
        }

        if (str_starts_with($permission, 'time-tracking.')
            || str_starts_with($permission, 'manager.work-time.')
            || str_starts_with($permission, 'admin.time-tracking.')
            || str_starts_with($permission, 'admin.work-time.')
        ) {
            return 'time_tracking';
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
