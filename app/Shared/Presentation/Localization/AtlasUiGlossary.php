<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Localization;

final class AtlasUiGlossary
{
    /**
     * @return array<string, array{
     *     technical_name: string,
     *     labels: array{singular: string, plural: string, menu: string, page: string, form: string},
     *     action_verbs: array<string, string>,
     *     status_labels: array<string, string>,
     *     bindings: array<string, string>
     * }>
     */
    public static function entries(): array
    {
        return [
            'dashboard' => self::entry('dashboard', 'glossary.dashboard', bindings: [
                'breadcrumbs.app_dashboard' => 'glossary.dashboard.page',
                'pages.dashboard.head_title' => 'glossary.dashboard.page',
                'pages.dashboard.title' => 'glossary.dashboard.page',
            ]),
            'user' => self::entry('user', 'glossary.user', [
                'create' => 'glossary.actions.create',
                'edit' => 'glossary.actions.edit',
                'save' => 'glossary.actions.save',
            ], [
                'active' => 'glossary.status.active',
                'inactive' => 'glossary.status.inactive',
            ], [
                'breadcrumbs.users' => 'glossary.user.plural',
                'navigation.users' => 'glossary.user.menu',
                'pages.admin.users.index.head_title' => 'glossary.user.page',
                'pages.admin.users.index.title' => 'glossary.user.page',
            ]),
            'team' => self::entry('team', 'glossary.team', [
                'create' => 'glossary.actions.create',
                'edit' => 'glossary.actions.edit',
                'activate' => 'glossary.actions.activate',
                'deactivate' => 'glossary.actions.deactivate',
            ], [
                'active' => 'glossary.status.active',
                'inactive' => 'glossary.status.inactive',
            ], [
                'breadcrumbs.teams' => 'glossary.team.plural',
                'navigation.teams' => 'glossary.team.menu',
                'pages.admin.teams.head_title' => 'glossary.team.page',
                'pages.admin.teams.title' => 'glossary.team.page',
            ]),
            'role' => self::entry('role', 'glossary.role', [
                'create' => 'glossary.actions.create',
                'edit' => 'glossary.actions.edit',
                'delete' => 'glossary.actions.delete',
            ], bindings: [
                'breadcrumbs.roles' => 'glossary.role.plural',
                'navigation.roles' => 'glossary.role.menu',
                'pages.admin.roles.head_title' => 'glossary.role.page',
                'pages.admin.roles.title' => 'glossary.role.page',
            ]),
            'authorization_preset' => self::entry('onboarding_package', 'glossary.authorization_preset', [
                'create' => 'glossary.actions.create',
                'edit' => 'glossary.actions.edit',
                'apply' => 'glossary.actions.apply',
            ], bindings: [
                'breadcrumbs.packages' => 'glossary.authorization_preset.plural',
                'navigation.packages' => 'glossary.authorization_preset.menu',
                'pages.admin.packages.head_title' => 'glossary.authorization_preset.page',
                'pages.admin.packages.title' => 'glossary.authorization_preset.page',
            ]),
            'permission' => self::entry('permission', 'glossary.permission', bindings: [
                'breadcrumbs.permissions' => 'glossary.permission.plural',
                'navigation.permissions' => 'glossary.permission.menu',
                'pages.admin.permissions.head_title' => 'glossary.permission.page',
                'pages.admin.permissions.title' => 'glossary.permission.page',
            ]),
            'direct_permission' => self::entry('direct_permission', 'glossary.direct_permission'),
            'email_address' => self::entry('email_address', 'glossary.email_address'),
            'manager' => self::entry('manager', 'glossary.manager'),
            'status' => self::entry('status', 'glossary.status_name', statusLabels: [
                'active' => 'glossary.status.active',
                'inactive' => 'glossary.status.inactive',
                'enabled' => 'glossary.status.enabled',
                'disabled' => 'glossary.status.disabled',
                'degraded' => 'glossary.status.degraded',
            ]),
            'activation' => self::entry('activation', 'glossary.activation', [
                'activate' => 'glossary.actions.activate',
                'deactivate' => 'glossary.actions.deactivate',
            ], [
                'enabled' => 'glossary.status.enabled',
                'disabled' => 'glossary.status.disabled',
            ]),
            'public_identifier' => self::entry('public_id', 'glossary.public_identifier'),
            'work_time' => self::entry('time_tracking', 'glossary.work_time', bindings: [
                'breadcrumbs.time_tracking' => 'glossary.work_time.page',
                'navigation.time_tracking' => 'glossary.work_time.menu',
            ]),
            'managed_process' => self::entry('managed_process', 'glossary.managed_process', bindings: [
                'breadcrumbs.managed_processes' => 'glossary.managed_process.plural',
                'navigation.managed_processes' => 'glossary.managed_process.menu',
                'pages.admin.managed_processes.head_title' => 'glossary.managed_process.page',
                'pages.admin.managed_processes.title' => 'glossary.managed_process.page',
            ]),
            'authorization_reason' => self::entry('authorization_reason', 'glossary.authorization_reason'),
        ];
    }

    /**
     * @param  array<string, string>  $actionVerbs
     * @param  array<string, string>  $statusLabels
     * @param  array<string, string>  $bindings
     * @return array{
     *     technical_name: string,
     *     labels: array{singular: string, plural: string, menu: string, page: string, form: string},
     *     action_verbs: array<string, string>,
     *     status_labels: array<string, string>,
     *     bindings: array<string, string>
     * }
     */
    private static function entry(
        string $technicalName,
        string $translationPrefix,
        array $actionVerbs = [],
        array $statusLabels = [],
        array $bindings = [],
    ): array {
        return [
            'technical_name' => $technicalName,
            'labels' => [
                'singular' => $translationPrefix.'.singular',
                'plural' => $translationPrefix.'.plural',
                'menu' => $translationPrefix.'.menu',
                'page' => $translationPrefix.'.page',
                'form' => $translationPrefix.'.form',
            ],
            'action_verbs' => $actionVerbs,
            'status_labels' => $statusLabels,
            'bindings' => $bindings,
        ];
    }
}
