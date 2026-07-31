import { usePage } from '@inertiajs/vue3';
import { IconCalendarTime, IconListDetails, IconPlayerPlay } from '@tabler/icons-vue';
import { computed } from 'vue';

import type { AtlasPageProps } from '../Types/inertia';
import type { ShellSubnavigationItem } from '../Types/navigation';

export function useManagedProcessSubnavigation(
    currentPath: string,
    t: (key: string, replacements?: Record<string, string | number>) => string,
) {
    const page = usePage<AtlasPageProps>();
    const isRunDetail = computed(() => /^\/admin\/managed-processes\/(?!definitions|schedules)([^/]+)$/.test(currentPath));

    return computed<ShellSubnavigationItem[]>(() => [
        {
            key: 'managed-processes.runs',
            label: t('pages.admin.managed_processes.nav.runs'),
            href: '/admin/managed-processes',
            icon: IconPlayerPlay,
            active: currentPath === '/admin/managed-processes' || isRunDetail.value,
            visible: page.props.auth.availableAdminRoutes.includes('admin.managed-processes.index'),
        },
        {
            key: 'managed-processes.definitions',
            label: t('pages.admin.managed_processes.nav.definitions'),
            href: '/admin/managed-processes/definitions',
            icon: IconListDetails,
            active:
                currentPath === '/admin/managed-processes/definitions' || currentPath.startsWith('/admin/managed-processes/definitions/'),
            visible: page.props.auth.availableAdminRoutes.includes('admin.managed-processes.definitions.index'),
        },
        {
            key: 'managed-processes.schedules',
            label: t('pages.admin.managed_processes.nav.schedules'),
            href: '/admin/managed-processes/schedules',
            icon: IconCalendarTime,
            active: currentPath === '/admin/managed-processes/schedules' || currentPath.startsWith('/admin/managed-processes/schedules/'),
            visible: page.props.auth.availableAdminRoutes.includes('admin.managed-processes.schedules.index'),
        },
    ]);
}

export function processStatusLabel(status: string, t: (key: string) => string): string {
    const key = `pages.admin.managed_processes.statuses.${status}`;

    return t(key) === key ? status : t(key);
}

export function processSourceLabel(source: string, t: (key: string) => string): string {
    const key = `pages.admin.managed_processes.sources.${source}`;

    return t(key) === key ? source : t(key);
}

export function processSeverityLabel(severity: string, t: (key: string) => string): string {
    const key = `pages.admin.managed_processes.severities.${severity}`;

    return t(key) === key ? severity : t(key);
}

export function yesNoOptions(t: (key: string) => string) {
    return [
        { value: 'all', label: t('pages.admin.managed_processes.all') },
        { value: 'yes', label: t('datatable.boolean.yes') },
        { value: 'no', label: t('datatable.boolean.no') },
    ];
}

export function allOptions(values: string[], label: string, valueLabel?: (value: string) => string) {
    return [
        { value: 'all', label },
        ...values.map((value) => ({
            value,
            label: valueLabel === undefined ? value : valueLabel(value),
        })),
    ];
}

export function jsonText(value: Record<string, unknown> | string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '{}';
    }

    if (typeof value === 'string') {
        try {
            return JSON.stringify(JSON.parse(value), null, 2);
        } catch {
            return value;
        }
    }

    return JSON.stringify(value, null, 2);
}
