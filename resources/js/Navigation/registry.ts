import {
    IconBell,
    IconBriefcase,
    IconCalendarTime,
    IconCalendarEvent,
    IconClockHour4,
    IconDatabase,
    IconFilePencil,
    IconFiles,
    IconFileText,
    IconFlag,
    IconGauge,
    IconHistory,
    IconKey,
    IconLayoutDashboard,
    IconListDetails,
    IconPackage,
    IconPlayerPause,
    IconPlayerPlay,
    IconPlugConnected,
    IconPuzzle,
    IconRoute,
    IconScale,
    IconSearch,
    IconServerCog,
    IconShieldCheck,
    IconShieldLock,
    IconUserCircle,
    IconUsers,
    IconUsersGroup,
} from '@tabler/icons-vue';
import type { Component } from 'vue';

import type { AtlasPageProps } from '../Types/inertia';
import type {
    NavigationGroup,
    NavigationNode,
    ShellMode,
    ShellModeLink,
    ShellSubnavigationItem,
    ShellSubnavigationKey,
} from '../Types/navigation';

type Translator = (key: string, replacements?: Record<string, string | number>) => string;
type AvailabilityKind = 'admin' | 'application';

interface NavigationItemDefinition {
    key: string;
    labelKey: string;
    href: string;
    icon: Component;
    availability: string | string[];
    availabilityKind: AvailabilityKind;
    modes: ShellMode[];
    activePrefixes?: string[];
    activePattern?: RegExp;
    exact?: boolean;
    external?: boolean;
}

interface NavigationGroupDefinition {
    key: string;
    labelKey: string;
    icon: Component;
    items: NavigationItemDefinition[];
}

interface SubnavigationDefinition {
    labelKey: string;
    items: Array<Omit<NavigationItemDefinition, 'modes'>>;
}

export interface NavigationRegistryContext {
    mode: ShellMode;
    currentPath: string;
    availableAdminRoutes: string[];
    availableApplicationRoutes: string[];
}

export interface ResolvedNavigationRegistry {
    groups: NavigationGroup[];
    modeLinks: ShellModeLink[];
    subnavigation: ShellSubnavigationItem[];
    subnavigationLabel: string;
    breadcrumbs: AtlasPageProps['navigation']['breadcrumbs'];
}

const item = (
    key: string,
    labelKey: string,
    href: string,
    icon: Component,
    availability: string | string[],
    availabilityKind: AvailabilityKind,
    modes: ShellMode[],
    options: Pick<NavigationItemDefinition, 'activePrefixes' | 'activePattern' | 'exact' | 'external'> = {},
): NavigationItemDefinition => ({ key, labelKey, href, icon, availability, availabilityKind, modes, ...options });

const groups: NavigationGroupDefinition[] = [
    {
        key: 'workspace',
        labelKey: 'navigation.group.workspace',
        icon: IconGauge,
        items: [
            item(
                'workspace.app-dashboard',
                'navigation.app_dashboard',
                '/',
                IconLayoutDashboard,
                'dashboard',
                'application',
                ['app', 'user', 'manager', 'admin'],
                { exact: true },
            ),
            item(
                'workspace.calendar',
                'navigation.calendar',
                '/calendar',
                IconCalendarEvent,
                'calendar.index',
                'application',
                ['app', 'user', 'manager', 'admin'],
                { activePrefixes: ['/calendar'] },
            ),
            item('workspace.user-profile', 'navigation.user_dashboard', '/user', IconUserCircle, 'users.profile', 'application', ['user'], {
                exact: true,
            }),
            item(
                'workspace.manager-panel',
                'navigation.manager_dashboard',
                '/manager',
                IconUsersGroup,
                'time-tracking.panels.manager',
                'application',
                ['manager'],
                { exact: true },
            ),
            item(
                'workspace.admin-dashboard',
                'navigation.admin_dashboard',
                '/admin',
                IconLayoutDashboard,
                'admin.system-status',
                'admin',
                ['admin'],
                { exact: true },
            ),
        ],
    },
    {
        key: 'my-matters',
        labelKey: 'navigation.group.my_matters',
        icon: IconUserCircle,
        items: [
            item(
                'my-matters.time-tracking',
                'navigation.time_tracking',
                '/user/work-time',
                IconClockHour4,
                'users.work-time',
                'application',
                ['user'],
                { exact: true },
            ),
            item(
                'my-matters.notifications',
                'navigation.notifications',
                '/user/notifications',
                IconBell,
                'users.notifications.index',
                'application',
                ['user'],
            ),
        ],
    },
    {
        key: 'manager-work-time',
        labelKey: 'navigation.group.work_time',
        icon: IconClockHour4,
        items: [
            item(
                'manager-work-time.summary',
                'navigation.work_time_daily',
                '/manager/work-time/summary',
                IconClockHour4,
                'manager.work-time.summary.index',
                'application',
                ['manager'],
            ),
            item(
                'manager-work-time.other-work',
                'navigation.work_time_other_work',
                '/manager/work-time/other-work',
                IconBriefcase,
                'manager.work-time.other-work.index',
                'application',
                ['manager'],
            ),
            item(
                'manager-work-time.breaks',
                'navigation.work_time_breaks',
                '/manager/work-time/breaks',
                IconPlayerPause,
                'manager.work-time.breaks.index',
                'application',
                ['manager'],
            ),
            item(
                'manager-work-time.corrections',
                'navigation.work_time_corrections',
                '/manager/work-time/corrections',
                IconFilePencil,
                'manager.work-time.corrections.index',
                'application',
                ['manager'],
            ),
            item(
                'manager-work-time.work-sessions',
                'navigation.work_time_sessions',
                '/manager/work-time/work-sessions',
                IconDatabase,
                'manager.work-time.work-sessions.index',
                'application',
                ['manager'],
            ),
        ],
    },
    {
        key: 'identity-access',
        labelKey: 'navigation.group.identity_access',
        icon: IconUsers,
        items: [
            item('identity-access.admin-users', 'navigation.users', '/admin/users', IconUsers, 'admin.users.index', 'admin', ['admin']),
            item('identity-access.admin-teams', 'navigation.teams', '/admin/teams', IconUsersGroup, 'admin.teams.index', 'admin', [
                'admin',
            ]),
            item(
                'identity-access.admin-audit',
                'navigation.audit_security',
                '/admin/audit',
                IconShieldCheck,
                'admin.audit.index',
                'admin',
                ['admin'],
            ),
            item(
                'identity-access.admin-roles',
                'navigation.roles',
                '/admin/authorization/roles',
                IconShieldLock,
                'admin.authorization.roles.index',
                'admin',
                ['admin'],
            ),
            item(
                'identity-access.admin-permissions',
                'navigation.permissions',
                '/admin/authorization/permissions',
                IconKey,
                'admin.authorization.permissions.index',
                'admin',
                ['admin'],
            ),
            item(
                'identity-access.admin-packages',
                'navigation.packages',
                '/admin/authorization/packages',
                IconPackage,
                'admin.authorization.packages.index',
                'admin',
                ['admin'],
            ),
        ],
    },
    {
        key: 'operational-areas',
        labelKey: 'navigation.group.operational_areas',
        icon: IconBriefcase,
        items: [
            item(
                'operational-areas.work-time',
                'navigation.group.work_time',
                '/admin/work-time/summary',
                IconClockHour4,
                [
                    'admin.work-time.summary.index',
                    'admin.work-time.other-work.index',
                    'admin.work-time.breaks.index',
                    'admin.work-time.corrections.index',
                    'admin.work-time.work-sessions.index',
                ],
                'admin',
                ['admin'],
                { activePrefixes: ['/admin/work-time'] },
            ),
        ],
    },
    {
        key: 'diagnostics',
        labelKey: 'navigation.group.diagnostics',
        icon: IconGauge,
        items: [
            item('diagnostics.pulse', 'navigation.pulse', '/admin/pulse', IconGauge, 'admin.pulse.view', 'admin', ['admin'], {
                external: true,
            }),
            item('diagnostics.telescope', 'navigation.telescope', '/telescope', IconSearch, 'admin.telescope.view', 'admin', ['admin'], {
                external: true,
            }),
        ],
    },
    {
        key: 'system-configuration',
        labelKey: 'navigation.group.system_configuration',
        icon: IconPuzzle,
        items: [
            item('system-configuration.admin-modules', 'navigation.modules', '/admin/modules', IconPuzzle, 'admin.modules.index', 'admin', [
                'admin',
            ]),
            item(
                'system-configuration.managed-processes',
                'navigation.managed_processes',
                '/admin/managed-processes',
                IconServerCog,
                'admin.managed-processes.index',
                'admin',
                ['admin'],
            ),
            item('system-configuration.queues', 'navigation.queues', '/admin/queues', IconRoute, 'admin.queues.index', 'admin', ['admin']),
            item('system-configuration.files', 'navigation.files', '/admin/files', IconFiles, 'admin.files.index', 'admin', ['admin']),
            item(
                'system-configuration.privacy-retention',
                'navigation.privacy_retention',
                '/admin/privacy-retention',
                IconShieldCheck,
                'admin.privacy-retention.index',
                'admin',
                ['admin'],
            ),
            item('system-configuration.logs', 'navigation.logs', '/admin/logs', IconFileText, 'admin.logs.index', 'admin', ['admin']),
            item(
                'system-configuration.feature-flags',
                'navigation.feature_flags',
                '/admin/feature-flags',
                IconFlag,
                'admin.feature-flags.index',
                'admin',
                ['admin'],
            ),
            item(
                'system-configuration.rate-limits',
                'navigation.rate_limits',
                '/admin/rate-limits',
                IconShieldLock,
                'admin.rate-limits.index',
                'admin',
                ['admin'],
            ),
            item(
                'system-configuration.integrations',
                'navigation.integrations',
                '/admin/integrations',
                IconPlugConnected,
                'admin.integrations.index',
                'admin',
                ['admin'],
            ),
            item('system-configuration.search', 'navigation.search', '/admin/search', IconSearch, 'admin.search.index', 'admin', ['admin']),
        ],
    },
];

const subnavigation: Record<ShellSubnavigationKey, SubnavigationDefinition> = {
    audit: {
        labelKey: 'pages.admin.audit.nav.label',
        items: [
            {
                key: 'audit.events',
                labelKey: 'pages.admin.audit.nav.events',
                href: '/admin/audit',
                icon: IconShieldCheck,
                availability: 'admin.audit.index',
                availabilityKind: 'admin',
                exact: true,
            },
            {
                key: 'audit.security',
                labelKey: 'pages.admin.audit.nav.security_history',
                href: '/admin/audit/security-history',
                icon: IconHistory,
                availability: 'admin.audit.security-history.index',
                availabilityKind: 'admin',
            },
        ],
    },
    'managed-processes': {
        labelKey: 'pages.admin.managed_processes.nav.label',
        items: [
            {
                key: 'managed-processes.runs',
                labelKey: 'pages.admin.managed_processes.nav.runs',
                href: '/admin/managed-processes',
                icon: IconPlayerPlay,
                availability: 'admin.managed-processes.index',
                availabilityKind: 'admin',
                activePattern: /^\/admin\/managed-processes(?:\/[^/]+)?$/,
                exact: true,
            },
            {
                key: 'managed-processes.definitions',
                labelKey: 'pages.admin.managed_processes.nav.definitions',
                href: '/admin/managed-processes/definitions',
                icon: IconListDetails,
                availability: 'admin.managed-processes.definitions.index',
                availabilityKind: 'admin',
            },
            {
                key: 'managed-processes.schedules',
                labelKey: 'pages.admin.managed_processes.nav.schedules',
                href: '/admin/managed-processes/schedules',
                icon: IconCalendarTime,
                availability: 'admin.managed-processes.schedules.index',
                availabilityKind: 'admin',
            },
        ],
    },
    'privacy-retention': {
        labelKey: 'pages.admin.privacy_retention.nav.label',
        items: [
            {
                key: 'privacy.coverage',
                labelKey: 'pages.admin.privacy_retention.nav.coverage',
                href: '/admin/privacy-retention',
                icon: IconShieldCheck,
                availability: 'admin.privacy-retention.index',
                availabilityKind: 'admin',
                exact: true,
            },
            {
                key: 'privacy.legal-holds',
                labelKey: 'pages.admin.privacy_retention.nav.legal_holds',
                href: '/admin/privacy-retention/legal-holds',
                icon: IconScale,
                availability: 'admin.privacy-retention.legal-holds.index',
                availabilityKind: 'admin',
            },
            {
                key: 'privacy.operations',
                labelKey: 'pages.admin.privacy_retention.nav.operations',
                href: '/admin/privacy-retention/operations',
                icon: IconHistory,
                availability: 'admin.privacy-retention.operations.index',
                availabilityKind: 'admin',
            },
        ],
    },
    'work-time': {
        labelKey: 'navigation.group.work_time',
        items: [
            {
                key: 'work-time.daily',
                labelKey: 'navigation.work_time_daily',
                href: '/admin/work-time/summary',
                icon: IconClockHour4,
                availability: 'admin.work-time.summary.index',
                availabilityKind: 'admin',
            },
            {
                key: 'work-time.other-work',
                labelKey: 'navigation.work_time_other_work',
                href: '/admin/work-time/other-work',
                icon: IconBriefcase,
                availability: 'admin.work-time.other-work.index',
                availabilityKind: 'admin',
            },
            {
                key: 'work-time.breaks',
                labelKey: 'navigation.work_time_breaks',
                href: '/admin/work-time/breaks',
                icon: IconPlayerPause,
                availability: 'admin.work-time.breaks.index',
                availabilityKind: 'admin',
            },
            {
                key: 'work-time.corrections',
                labelKey: 'navigation.work_time_corrections',
                href: '/admin/work-time/corrections',
                icon: IconFilePencil,
                availability: 'admin.work-time.corrections.index',
                availabilityKind: 'admin',
            },
            {
                key: 'work-time.work-sessions',
                labelKey: 'navigation.work_time_sessions',
                href: '/admin/work-time/work-sessions',
                icon: IconDatabase,
                availability: 'admin.work-time.work-sessions.index',
                availabilityKind: 'admin',
            },
        ],
    },
};

const modeDefinitions: Array<Omit<NavigationItemDefinition, 'modes'>> = [
    {
        key: 'app',
        labelKey: 'navigation.app_dashboard',
        href: '/',
        icon: IconLayoutDashboard,
        availability: 'dashboard',
        availabilityKind: 'application',
        exact: true,
    },
    {
        key: 'user',
        labelKey: 'navigation.user_panel',
        href: '/user',
        icon: IconUserCircle,
        availability: 'users.profile',
        availabilityKind: 'application',
    },
    {
        key: 'manager',
        labelKey: 'navigation.manager_panel',
        href: '/manager',
        icon: IconUsersGroup,
        availability: 'time-tracking.panels.manager',
        availabilityKind: 'application',
    },
    {
        key: 'admin',
        labelKey: 'navigation.admin_panel',
        href: '/admin',
        icon: IconShieldLock,
        availability: 'admin.system-status',
        availabilityKind: 'admin',
    },
];

const pathOnly = (url: string): string => {
    const rawPath = /^https?:\/\//.test(url) ? new URL(url).pathname : (url.split('?')[0] ?? '/');

    return rawPath.replace(/\/$/, '') || '/';
};

function isAvailable(
    definition: Pick<NavigationItemDefinition, 'availability' | 'availabilityKind'>,
    context: NavigationRegistryContext,
): boolean {
    const available = definition.availabilityKind === 'admin' ? context.availableAdminRoutes : context.availableApplicationRoutes;
    const candidates = Array.isArray(definition.availability) ? definition.availability : [definition.availability];

    return candidates.some((candidate) => available.includes(candidate));
}

function isActive(
    definition: Pick<NavigationItemDefinition, 'href' | 'activePrefixes' | 'activePattern' | 'exact'>,
    currentPath: string,
): boolean {
    const current = pathOnly(currentPath);
    const href = pathOnly(definition.href);

    if (definition.activePattern?.test(current)) {
        return true;
    }

    if (definition.activePrefixes?.some((prefix) => current.startsWith(prefix))) {
        return true;
    }

    return definition.exact ? current === href : current === href || current.startsWith(`${href}/`);
}

function resolveItem(definition: NavigationItemDefinition, context: NavigationRegistryContext, t: Translator): NavigationNode {
    return {
        key: definition.key,
        label: t(definition.labelKey),
        href: workTimeHrefWithCurrentQuery(definition.href, context.currentPath),
        icon: definition.icon,
        external: definition.external,
        active: isActive(definition, context.currentPath),
    };
}

function workTimeHrefWithCurrentQuery(href: string, currentPath: string): string {
    const currentUrl = new URL(currentPath, 'http://atlas.local');
    const targetUrl = new URL(href, 'http://atlas.local');
    const sameWorkTimeSurface = ['/admin/work-time/', '/manager/work-time/'].some(
        (prefix) => currentUrl.pathname.startsWith(prefix) && targetUrl.pathname.startsWith(prefix),
    );

    return sameWorkTimeSurface && currentUrl.search !== '' ? `${targetUrl.pathname}${currentUrl.search}` : href;
}

function canonicalLabels(t: Translator): Map<string, string> {
    const definitions = [...groups.flatMap((group) => group.items), ...Object.values(subnavigation).flatMap((section) => section.items)];

    return new Map(definitions.map((definition) => [pathOnly(definition.href), t(definition.labelKey)]));
}

export function resolveNavigationRegistry(
    context: NavigationRegistryContext,
    section: ShellSubnavigationKey | undefined,
    sourceBreadcrumbs: AtlasPageProps['navigation']['breadcrumbs'],
    t: Translator,
): ResolvedNavigationRegistry {
    const resolvedGroups = groups
        .map((group): NavigationGroup => ({
            key: group.key,
            label: t(group.labelKey),
            icon: group.icon,
            items: group.items
                .filter((definition) => definition.modes.includes(context.mode) && isAvailable(definition, context))
                .map((definition) => resolveItem(definition, context, t)),
        }))
        .filter((group) => group.items.length > 0);
    const sectionDefinition = section ? subnavigation[section] : undefined;
    const resolvedSubnavigation = (sectionDefinition?.items ?? [])
        .filter((definition) => isAvailable(definition, context))
        .map((definition): ShellSubnavigationItem => ({
            key: definition.key,
            label: t(definition.labelKey),
            href: section === 'work-time' ? workTimeHrefWithCurrentQuery(definition.href, context.currentPath) : definition.href,
            icon: definition.icon,
            active: isActive(definition, context.currentPath),
        }));
    const labels = canonicalLabels(t);

    return {
        groups: resolvedGroups,
        modeLinks: modeDefinitions
            .filter((definition) => isAvailable(definition, context))
            .map((definition): ShellModeLink => ({
                key: definition.key as ShellMode,
                label: t(definition.labelKey),
                href: definition.href,
                icon: definition.icon,
                active: definition.key === context.mode,
            })),
        subnavigation: resolvedSubnavigation,
        subnavigationLabel: sectionDefinition ? t(sectionDefinition.labelKey) : '',
        breadcrumbs: sourceBreadcrumbs.map((breadcrumb) => {
            if (!breadcrumb.url) {
                return breadcrumb;
            }

            const label = labels.get(pathOnly(breadcrumb.url));

            return label ? { ...breadcrumb, label } : breadcrumb;
        }),
    };
}

export const navigationRegistryDefinitions = { groups, subnavigation, modeDefinitions } as const;
