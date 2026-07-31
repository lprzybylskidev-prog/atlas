<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    IconChevronDown,
    IconFlag,
    IconFiles,
    IconGauge,
    IconFileText,
    IconPlugConnected,
    IconKey,
    IconLayoutDashboard,
    IconPackage,
    IconPuzzle,
    IconRoute,
    IconSearch,
    IconServerCog,
    IconShieldCheck,
    IconShieldLock,
    IconSitemap,
    IconUsers,
    IconUsersGroup,
} from '@tabler/icons-vue';
import type { FunctionalComponent } from 'vue';
import { computed } from 'vue';

import AtlasLogo from './AtlasLogo.vue';
import SidebarNavNode from './SidebarNavNode.vue';
import Tooltip from './Tooltip.vue';
import { useSidebar } from '../Composables/useSidebar';
import { useTranslator } from '../Localization/translator';
import type { AtlasPageProps } from '../Types/inertia';
import type { NavigationNode } from '../Types/navigation';

interface NavigationGroup {
    key: string;
    label: string;
    icon: FunctionalComponent;
    items: NavigationNode[];
}

const props = defineProps<{
    currentPath: string;
    mode?: 'app' | 'admin';
    uiLocale?: string;
}>();

const { isNavigationNodeExpanded, isSidebarCollapsed, isSidebarTextVisible, setNavigationNodeExpanded } = useSidebar();
const { t } = useTranslator(props.uiLocale);
const page = usePage<AtlasPageProps>();

function canSeeAdminRoute(route: string): boolean {
    return page.props.auth.availableAdminRoutes.includes(route);
}

const groups = computed<NavigationGroup[]>(() => {
    const workspace: NavigationGroup = {
        key: 'workspace',
        label: t('navigation.group.workspace'),
        icon: IconGauge,
        items: [
            {
                key: 'workspace.dashboard',
                label: t('navigation.app_dashboard'),
                href: '/',
                icon: IconGauge,
                active: props.currentPath === '/',
            },
        ],
    };

    if (props.mode !== 'admin') {
        return [workspace];
    }

    return [
        {
            ...workspace,
            items: [
                ...workspace.items,
                {
                    key: 'workspace.admin-dashboard',
                    label: t('navigation.admin_dashboard'),
                    href: '/admin',
                    icon: IconLayoutDashboard,
                    active: props.currentPath === '/admin',
                    visible: canSeeAdminRoute('admin.system-status'),
                },
            ].filter((item) => item.visible !== false),
        },
        {
            key: 'identity-access',
            label: t('navigation.group.identity_access'),
            icon: IconUsers,
            items: [
                {
                    key: 'identity-access.admin-users',
                    label: t('navigation.users'),
                    href: '/admin/users',
                    icon: IconUsers,
                    active: props.currentPath.startsWith('/admin/users'),
                    visible: canSeeAdminRoute('admin.users.index'),
                },
                {
                    key: 'identity-access.admin-teams',
                    label: t('navigation.teams'),
                    href: '/admin/teams',
                    icon: IconUsersGroup,
                    active: props.currentPath.startsWith('/admin/teams'),
                    visible: canSeeAdminRoute('admin.teams.index'),
                },
                {
                    key: 'identity-access.admin-managers',
                    label: t('navigation.managers'),
                    href: '/admin/managers',
                    icon: IconSitemap,
                    active: props.currentPath.startsWith('/admin/managers'),
                    visible: canSeeAdminRoute('admin.managers.index'),
                },
                {
                    key: 'identity-access.admin-audit',
                    label: t('navigation.audit_security'),
                    href: '/admin/audit',
                    icon: IconShieldCheck,
                    active: props.currentPath.startsWith('/admin/audit'),
                    visible: canSeeAdminRoute('admin.audit.index'),
                },
                {
                    key: 'identity-access.admin-roles',
                    label: t('navigation.roles'),
                    href: '/admin/authorization/roles',
                    icon: IconShieldLock,
                    active: props.currentPath.startsWith('/admin/authorization/roles'),
                    visible: canSeeAdminRoute('admin.authorization.roles.index'),
                },
                {
                    key: 'identity-access.admin-permissions',
                    label: t('navigation.permissions'),
                    href: '/admin/authorization/permissions',
                    icon: IconKey,
                    active: props.currentPath.startsWith('/admin/authorization/permissions'),
                    visible: canSeeAdminRoute('admin.authorization.permissions.index'),
                },
                {
                    key: 'identity-access.admin-packages',
                    label: t('navigation.packages'),
                    href: '/admin/authorization/packages',
                    icon: IconPackage,
                    active: props.currentPath.startsWith('/admin/authorization/packages'),
                    visible: canSeeAdminRoute('admin.authorization.packages.index'),
                },
            ].filter((item) => item.visible !== false),
        },
        {
            key: 'diagnostics',
            label: t('navigation.group.diagnostics'),
            icon: IconGauge,
            items: [
                {
                    key: 'diagnostics.pulse',
                    label: t('navigation.pulse'),
                    href: '/admin/pulse',
                    icon: IconGauge,
                    external: true,
                    active: props.currentPath.startsWith('/admin/pulse'),
                    visible: canSeeAdminRoute('admin.pulse.view'),
                },
                {
                    key: 'diagnostics.telescope',
                    label: t('navigation.telescope'),
                    href: '/telescope',
                    icon: IconSearch,
                    external: true,
                    active: props.currentPath.startsWith('/telescope'),
                    visible: canSeeAdminRoute('admin.telescope.view'),
                },
            ].filter((item) => item.visible !== false),
        },
        {
            key: 'system-configuration',
            label: t('navigation.group.system_configuration'),
            icon: IconPuzzle,
            items: [
                {
                    key: 'system-configuration.admin-modules',
                    label: t('navigation.modules'),
                    href: '/admin/modules',
                    icon: IconPuzzle,
                    active: props.currentPath.startsWith('/admin/modules'),
                    visible: canSeeAdminRoute('admin.modules.index'),
                },
                {
                    key: 'system-configuration.managed-processes',
                    label: t('navigation.managed_processes'),
                    href: '/admin/managed-processes',
                    icon: IconServerCog,
                    active: props.currentPath.startsWith('/admin/managed-processes'),
                    visible: canSeeAdminRoute('admin.managed-processes.index'),
                },
                {
                    key: 'system-configuration.queues',
                    label: t('navigation.queues'),
                    href: '/admin/queues',
                    icon: IconRoute,
                    active: props.currentPath.startsWith('/admin/queues'),
                    visible: canSeeAdminRoute('admin.queues.index'),
                },
                {
                    key: 'system-configuration.files',
                    label: t('navigation.files'),
                    href: '/admin/files',
                    icon: IconFiles,
                    active: props.currentPath.startsWith('/admin/files'),
                    visible: canSeeAdminRoute('admin.files.index'),
                },
                {
                    key: 'system-configuration.privacy-retention',
                    label: t('navigation.privacy_retention'),
                    href: '/admin/privacy-retention',
                    icon: IconShieldCheck,
                    active: props.currentPath.startsWith('/admin/privacy-retention'),
                    visible: canSeeAdminRoute('admin.privacy-retention.index'),
                },
                {
                    key: 'system-configuration.logs',
                    label: t('navigation.logs'),
                    href: '/admin/logs',
                    icon: IconFileText,
                    active: props.currentPath.startsWith('/admin/logs'),
                    visible: canSeeAdminRoute('admin.logs.index'),
                },
                {
                    key: 'system-configuration.feature-flags',
                    label: t('navigation.feature_flags'),
                    href: '/admin/feature-flags',
                    icon: IconFlag,
                    active: props.currentPath.startsWith('/admin/feature-flags'),
                    visible: canSeeAdminRoute('admin.feature-flags.index'),
                },
                {
                    key: 'system-configuration.rate-limits',
                    label: t('navigation.rate_limits'),
                    href: '/admin/rate-limits',
                    icon: IconShieldLock,
                    active: props.currentPath.startsWith('/admin/rate-limits'),
                    visible: canSeeAdminRoute('admin.rate-limits.index'),
                },
                {
                    key: 'system-configuration.integrations',
                    label: t('navigation.integrations'),
                    href: '/admin/integrations',
                    icon: IconPlugConnected,
                    active: props.currentPath.startsWith('/admin/integrations'),
                    visible: canSeeAdminRoute('admin.integrations.index'),
                },
                {
                    key: 'system-configuration.search',
                    label: t('navigation.search'),
                    href: '/admin/search',
                    icon: IconSearch,
                    active: props.currentPath.startsWith('/admin/search'),
                    visible: canSeeAdminRoute('admin.search.index'),
                },
            ].filter((item) => item.visible !== false),
        },
    ].filter((group) => group.items.length > 0);
});

function updateExpandedNavigationState(key: string, event: Event): void {
    const target = event.currentTarget;

    if (target instanceof HTMLDetailsElement) {
        setNavigationNodeExpanded(key, target.open);
    }
}
</script>

<template>
    <aside
        class="hidden min-h-screen shrink-0 overflow-visible border-r border-zinc-200 bg-white transition-[width] duration-300 ease-in-out lg:block dark:border-zinc-800 dark:bg-zinc-950"
        :class="isSidebarCollapsed ? 'w-[5.25rem]' : 'w-72'"
    >
        <div class="flex h-16 items-center justify-start border-b border-zinc-200 px-4 dark:border-zinc-800">
            <AtlasLogo
                :show-text="isSidebarTextVisible"
                animate-text
                :ui-locale="uiLocale"
                class="transition-transform duration-300 ease-in-out will-change-transform"
                :style="{ transform: isSidebarCollapsed ? 'translateX(0.5rem)' : 'translateX(0)' }"
            />
        </div>

        <nav v-if="mode !== 'admin'" class="space-y-1 px-3 py-4" :aria-label="t('navigation.aria.main')">
            <Tooltip v-for="item in groups[0]?.items ?? []" :key="item.key" :text="item.label" placement="right" class="w-full">
                <Link
                    :href="item.href ?? '#'"
                    class="group relative flex h-11 w-full items-center rounded-lg text-sm font-medium transition"
                    :class="[
                        item.active
                            ? 'bg-teal-50 text-teal-900 ring-1 ring-teal-100 dark:bg-teal-950 dark:text-teal-100 dark:ring-teal-900'
                            : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-50',
                        isSidebarTextVisible ? 'gap-3 px-3' : 'justify-center gap-0 px-0',
                    ]"
                >
                    <component
                        :is="item.icon"
                        aria-hidden="true"
                        class="h-5 w-5 shrink-0 transition-transform duration-300 ease-in-out"
                        :stroke-width="1.8"
                    />
                    <span
                        class="overflow-hidden truncate whitespace-nowrap transition-[max-width,opacity,transform] duration-300 ease-in-out"
                        :class="
                            isSidebarTextVisible
                                ? 'max-w-40 translate-x-0 opacity-100'
                                : 'pointer-events-none max-w-0 -translate-x-1 opacity-0'
                        "
                    >
                        {{ item.label }}
                    </span>
                </Link>
            </Tooltip>
        </nav>

        <nav v-else class="space-y-3 px-3 py-4" :aria-label="t('navigation.aria.main')">
            <details
                v-for="group in groups"
                :key="group.key"
                class="group/nav"
                :open="isNavigationNodeExpanded(group.key)"
                @toggle="updateExpandedNavigationState(group.key, $event)"
            >
                <summary
                    class="group/main-nav relative flex h-9 w-full cursor-pointer list-none items-center rounded-lg border border-zinc-200/70 bg-zinc-50/70 text-xs font-semibold text-zinc-500 transition-[padding,color,background-color,border-color] duration-300 ease-in-out hover:border-zinc-300 hover:bg-zinc-100 hover:text-zinc-800 dark:border-zinc-800/80 dark:bg-zinc-900/45 dark:text-zinc-300 dark:hover:border-zinc-700 dark:hover:bg-zinc-900 dark:hover:text-zinc-100 [&::-webkit-details-marker]:hidden"
                    :class="isSidebarTextVisible ? 'justify-between px-3 uppercase' : 'justify-center gap-0.5 px-1'"
                >
                    <Tooltip :text="group.label" placement="right" :full-width="isSidebarTextVisible">
                        <span
                            class="flex min-w-0 items-center transition-[gap,transform] duration-300 ease-in-out"
                            :class="isSidebarTextVisible ? 'gap-2' : 'gap-0'"
                        >
                            <component
                                :is="group.icon"
                                aria-hidden="true"
                                class="h-5 w-5 shrink-0 transition-transform duration-300 ease-in-out"
                                :stroke-width="1.8"
                            />
                            <span
                                class="overflow-hidden truncate whitespace-nowrap transition-[max-width,opacity,transform] duration-300 ease-in-out"
                                :class="
                                    isSidebarTextVisible
                                        ? 'max-w-44 translate-x-0 opacity-100'
                                        : 'pointer-events-none max-w-0 -translate-x-1 opacity-0'
                                "
                            >
                                {{ group.label }}
                            </span>
                        </span>
                    </Tooltip>
                    <IconChevronDown
                        aria-hidden="true"
                        class="h-4 w-4 shrink-0 text-zinc-400 transition-transform duration-300 ease-in-out dark:text-zinc-500"
                        :class="[isNavigationNodeExpanded(group.key) ? 'rotate-180' : 'rotate-0', { 'h-3.5 w-3.5': isSidebarCollapsed }]"
                        :stroke-width="1.8"
                    />
                </summary>
                <div class="space-y-1 pt-2" :class="isSidebarTextVisible ? 'pl-2' : ''">
                    <SidebarNavNode
                        v-for="item in group.items"
                        :key="item.key"
                        :node="item"
                        :collapsed="isSidebarCollapsed"
                        :text-visible="isSidebarTextVisible"
                        :depth="0"
                    />
                </div>
            </details>
        </nav>
    </aside>
</template>
