<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    IconFlag,
    IconFiles,
    IconFileText,
    IconGauge,
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
    IconX,
} from '@tabler/icons-vue';
import { computed } from 'vue';

import AtlasLogo from './AtlasLogo.vue';
import { useTranslator } from '../Localization/translator';
import type { AtlasPageProps } from '../Types/inertia';

interface MobileNavigationItem {
    label: string;
    href: string;
    icon: typeof IconGauge;
    external?: boolean;
    visible?: boolean;
}

interface MobileNavigationGroup {
    label: string;
    items: MobileNavigationItem[];
}

const props = defineProps<{
    open: boolean;
    mode?: 'app' | 'admin';
    uiLocale?: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useTranslator(props.uiLocale);
const page = usePage<AtlasPageProps>();

function canSeeAdminRoute(route: string): boolean {
    return page.props.auth.availableAdminRoutes.includes(route);
}

const groups = computed<MobileNavigationGroup[]>(() => {
    const workspace: MobileNavigationGroup = {
        label: t('navigation.group.workspace'),
        items: [{ label: t('navigation.app_dashboard'), href: '/', icon: IconGauge }],
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
                    label: t('navigation.admin_dashboard'),
                    href: '/admin',
                    icon: IconLayoutDashboard,
                    visible: canSeeAdminRoute('admin.system-status'),
                },
            ],
        },
        {
            label: t('navigation.group.identity_access'),
            items: [
                {
                    label: t('navigation.users'),
                    href: '/admin/users',
                    icon: IconUsers,
                    visible: canSeeAdminRoute('admin.users.index'),
                },
                {
                    label: t('navigation.teams'),
                    href: '/admin/teams',
                    icon: IconUsersGroup,
                    visible: canSeeAdminRoute('admin.teams.index'),
                },
                {
                    label: t('navigation.managers'),
                    href: '/admin/managers',
                    icon: IconSitemap,
                    visible: canSeeAdminRoute('admin.managers.index'),
                },
                {
                    label: t('navigation.audit_security'),
                    href: '/admin/audit',
                    icon: IconShieldCheck,
                    visible: canSeeAdminRoute('admin.audit.index'),
                },
                {
                    label: t('navigation.roles'),
                    href: '/admin/authorization/roles',
                    icon: IconShieldLock,
                    visible: canSeeAdminRoute('admin.authorization.roles.index'),
                },
                {
                    label: t('navigation.permissions'),
                    href: '/admin/authorization/permissions',
                    icon: IconKey,
                    visible: canSeeAdminRoute('admin.authorization.permissions.index'),
                },
                {
                    label: t('navigation.packages'),
                    href: '/admin/authorization/packages',
                    icon: IconPackage,
                    visible: canSeeAdminRoute('admin.authorization.packages.index'),
                },
            ],
        },
        {
            label: t('navigation.group.diagnostics'),
            items: [
                {
                    label: t('navigation.pulse'),
                    href: '/admin/pulse',
                    icon: IconGauge,
                    external: true,
                    visible: canSeeAdminRoute('admin.pulse.view'),
                },
                {
                    label: t('navigation.telescope'),
                    href: '/telescope',
                    icon: IconSearch,
                    external: true,
                    visible: canSeeAdminRoute('admin.telescope.view'),
                },
            ],
        },
        {
            label: t('navigation.group.system_configuration'),
            items: [
                {
                    label: t('navigation.modules'),
                    href: '/admin/modules',
                    icon: IconPuzzle,
                    visible: canSeeAdminRoute('admin.modules.index'),
                },
                {
                    label: t('navigation.managed_processes'),
                    href: '/admin/managed-processes',
                    icon: IconServerCog,
                    visible: canSeeAdminRoute('admin.managed-processes.index'),
                },
                {
                    label: t('navigation.queues'),
                    href: '/admin/queues',
                    icon: IconRoute,
                    visible: canSeeAdminRoute('admin.queues.index'),
                },
                {
                    label: t('navigation.files'),
                    href: '/admin/files',
                    icon: IconFiles,
                    visible: canSeeAdminRoute('admin.files.index'),
                },
                {
                    label: t('navigation.privacy_retention'),
                    href: '/admin/privacy-retention',
                    icon: IconShieldCheck,
                    visible: canSeeAdminRoute('admin.privacy-retention.index'),
                },
                {
                    label: t('navigation.logs'),
                    href: '/admin/logs',
                    icon: IconFileText,
                    visible: canSeeAdminRoute('admin.logs.index'),
                },
                {
                    label: t('navigation.feature_flags'),
                    href: '/admin/feature-flags',
                    icon: IconFlag,
                    visible: canSeeAdminRoute('admin.feature-flags.index'),
                },
                {
                    label: t('navigation.rate_limits'),
                    href: '/admin/rate-limits',
                    icon: IconShieldLock,
                    visible: canSeeAdminRoute('admin.rate-limits.index'),
                },
                {
                    label: t('navigation.integrations'),
                    href: '/admin/integrations',
                    icon: IconPlugConnected,
                    visible: canSeeAdminRoute('admin.integrations.index'),
                },
                {
                    label: t('navigation.search'),
                    href: '/admin/search',
                    icon: IconSearch,
                    visible: canSeeAdminRoute('admin.search.index'),
                },
            ],
        },
    ]
        .map((group) => ({ ...group, items: group.items.filter((item) => item.visible !== false) }))
        .filter((group) => group.items.length > 0);
});
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
        <button type="button" class="absolute inset-0 bg-zinc-950/45" :aria-label="t('actions.close_navigation')" @click="emit('close')" />
        <div class="absolute inset-y-0 left-0 flex w-[min(22rem,calc(100vw-2rem))] flex-col bg-white shadow-2xl dark:bg-zinc-950">
            <div class="flex h-16 items-center justify-between border-b border-zinc-200 px-4 dark:border-zinc-800">
                <AtlasLogo :ui-locale="uiLocale" />
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-900 dark:hover:text-zinc-50"
                    :aria-label="t('actions.close_navigation')"
                    @click="emit('close')"
                >
                    <IconX aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                </button>
            </div>
            <nav v-if="mode !== 'admin'" class="space-y-1 p-4" :aria-label="t('navigation.aria.mobile')">
                <Link
                    v-for="item in groups[0]?.items ?? []"
                    :key="item.label"
                    :href="item.href"
                    class="flex h-12 items-center gap-3 rounded-lg px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-900"
                    @click="emit('close')"
                >
                    <component :is="item.icon" aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                    {{ item.label }}
                </Link>
            </nav>

            <nav v-else class="space-y-4 p-4" :aria-label="t('navigation.aria.mobile')">
                <details v-for="group in groups" :key="group.label" open>
                    <summary class="list-none px-3 text-xs font-semibold uppercase text-zinc-400">{{ group.label }}</summary>
                    <div class="mt-2 space-y-1">
                        <component
                            :is="item.external ? 'a' : Link"
                            v-for="item in group.items"
                            :key="item.label"
                            :href="item.href"
                            :target="item.external ? '_blank' : undefined"
                            :rel="item.external ? 'noopener noreferrer' : undefined"
                            class="flex h-12 items-center gap-3 rounded-lg px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-900"
                            @click="emit('close')"
                        >
                            <component :is="item.icon" aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                            {{ item.label }}
                        </component>
                    </div>
                </details>
            </nav>
        </div>
    </div>
</template>
