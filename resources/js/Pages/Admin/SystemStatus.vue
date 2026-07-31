<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    IconActivityHeartbeat,
    IconAlertTriangle,
    IconBox,
    IconBrandLaravel,
    IconBrandPhp,
    IconBuildingCommunity,
    IconBell,
    IconCalendarCode,
    IconCheck,
    IconClock,
    IconCode,
    IconCpu,
    IconDatabase,
    IconFileTypePdf,
    IconFileExport,
    IconFiles,
    IconFlag,
    IconFolder,
    IconHeartbeat,
    IconHistory,
    IconLayoutDashboard,
    IconPlugConnected,
    IconPackage,
    IconReportAnalytics,
    IconSearch,
    IconServer,
    IconServerCog,
    IconSettings,
    IconShieldCheck,
    IconShieldLock,
    IconUpload,
    IconUserCircle,
    IconUsers,
    IconVersions,
    IconWorld,
    IconX,
} from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed } from 'vue';

import PageStack from '../../Components/PageStack.vue';
import OperationalMetricTile from '../../Components/OperationalMetricTile.vue';
import OperationalTile from '../../Components/OperationalTile.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';
import { formatDateTime, formatNumber } from '../../Utils/formatters';
import { moduleLabel } from '../../Utils/moduleLabels';

type DashboardStatus = 'healthy' | 'degraded' | 'unhealthy' | 'inactive' | 'unavailable' | 'info' | string;

interface ExternalMechanism {
    key: string;
    status: DashboardStatus;
    blocking: boolean;
    metadata: Record<string, boolean | number | string | null>;
}

interface DashboardModule {
    key: string;
    category: string;
    status: DashboardStatus;
    effectiveEnabled: boolean;
    technicallyAvailable: boolean;
    issueCount: number;
    issue: {
        severity: string;
        label: string;
        description: string;
        value?: number | string | null;
    } | null;
}

interface DashboardPayload {
    generatedAt: string;
    release: {
        version: string | null;
        id: string | null;
        environment: string | null;
        laravelVersion: string | null;
        phpVersion: string | null;
        timezone: string | null;
        runtime: string | null;
        deployedAt: string | null;
        deployedBy: string | null;
        source: string | null;
    };
    externalMechanisms: {
        status: DashboardStatus;
        checkedAt: string | null;
        blockingFailures: number;
        degradedFailures: number;
        items: ExternalMechanism[];
    };
    modules: {
        active: number;
        total: number;
        needingAttention: number;
        failedActivationSchedules: number;
        scheduledActivationChanges: number;
        items: DashboardModule[];
    };
}

const props = defineProps<{
    dashboard: DashboardPayload;
}>();

const { locale, t } = useTranslator();
const localeCode = computed(() => (locale.value === 'pl' ? 'pl-PL' : 'en-US'));
const releaseDetails = computed(() =>
    [
        { label: t('pages.admin.dashboard.release.version'), value: props.dashboard.release.version, icon: IconVersions },
        { label: t('pages.admin.dashboard.release.id'), value: props.dashboard.release.id, mono: true, icon: IconPackage },
        { label: t('pages.admin.dashboard.release.environment'), value: props.dashboard.release.environment, icon: IconWorld },
        { label: t('pages.admin.dashboard.release.laravel'), value: props.dashboard.release.laravelVersion, icon: IconBrandLaravel },
        { label: t('pages.admin.dashboard.release.php'), value: props.dashboard.release.phpVersion, icon: IconBrandPhp },
        { label: t('pages.admin.dashboard.release.timezone'), value: props.dashboard.release.timezone, icon: IconClock },
        { label: t('pages.admin.dashboard.release.runtime'), value: props.dashboard.release.runtime, icon: IconCpu },
        {
            label: t('pages.admin.dashboard.release.deployed_at'),
            value: props.dashboard.release.deployedAt ? formatDateTime(props.dashboard.release.deployedAt, localeCode.value) : null,
            icon: IconCalendarCode,
        },
        { label: t('pages.admin.dashboard.release.deployed_by'), value: props.dashboard.release.deployedBy, icon: IconUserCircle },
        { label: t('pages.admin.dashboard.release.source'), value: props.dashboard.release.source, mono: true, icon: IconCode },
    ].filter((item) => item.value !== null && item.value !== ''),
);
const externalRows = computed(() => props.dashboard.externalMechanisms.items);
const moduleRows = computed(() => [
    ...props.dashboard.modules.items.filter((module) => module.status !== 'healthy'),
    ...props.dashboard.modules.items.filter((module) => module.status === 'healthy'),
]);
const moduleCounters = computed(() => [
    {
        label: t('pages.admin.dashboard.modules.active'),
        value: `${formatNumber(props.dashboard.modules.active, localeCode.value)} / ${formatNumber(props.dashboard.modules.total, localeCode.value)}`,
    },
    {
        label: t('pages.admin.dashboard.modules.needing_attention'),
        value: formatNumber(props.dashboard.modules.needingAttention, localeCode.value),
    },
    {
        label: t('pages.admin.dashboard.modules.activation_failures'),
        value: formatNumber(props.dashboard.modules.failedActivationSchedules, localeCode.value),
    },
]);

function statusTone(status: DashboardStatus): 'neutral' | 'info' | 'success' | 'warning' | 'danger' {
    if (status === 'healthy') {
        return 'success';
    }

    if (status === 'degraded' || status === 'inactive') {
        return 'warning';
    }

    if (status === 'unhealthy' || status === 'unavailable' || status === 'failed') {
        return 'danger';
    }

    return 'info';
}

function statusLabel(status: DashboardStatus): string {
    if (['healthy', 'degraded', 'unhealthy', 'inactive', 'unavailable', 'failed', 'info'].includes(status)) {
        return t(`pages.admin.dashboard.status.${status}`);
    }

    return t('pages.admin.dashboard.status.unknown');
}

function mechanismLabel(key: string): string {
    const supported = ['postgresql', 'redis', 'storage', 'meilisearch', 'clamav', 'chromium-pdf'];

    return supported.includes(key) ? t(`pages.admin.dashboard.external.${key.replaceAll('-', '_')}`) : key;
}

function mechanismIcon(key: string): Component {
    const icons: Record<string, Component> = {
        postgresql: IconDatabase,
        redis: IconServer,
        storage: IconFolder,
        meilisearch: IconSearch,
        clamav: IconShieldCheck,
        'chromium-pdf': IconFileTypePdf,
    };

    return icons[key] ?? IconActivityHeartbeat;
}

function statusIcon(status: DashboardStatus): Component {
    if (status === 'healthy') {
        return IconCheck;
    }

    if (status === 'degraded' || status === 'inactive' || status === 'info') {
        return IconAlertTriangle;
    }

    return IconX;
}

function moduleIcon(key: string): Component {
    const icons: Record<string, Component> = {
        audit: IconHistory,
        authorization: IconShieldLock,
        exports: IconFileExport,
        feature_flags: IconFlag,
        files: IconFiles,
        health: IconHeartbeat,
        identity: IconUserCircle,
        imports: IconUpload,
        integrations: IconPlugConnected,
        managed_processes: IconServerCog,
        notifications: IconBell,
        privacy: IconShieldCheck,
        reports: IconReportAnalytics,
        search: IconSearch,
        settings: IconSettings,
        teams: IconBuildingCommunity,
        users: IconUsers,
    };

    return icons[key] ?? IconServerCog;
}

function moduleIssueLabel(module: DashboardModule): string {
    if (module.issue === null) {
        return t(`pages.admin.dashboard.status.${module.status}`);
    }

    const key = module.issue.label.toLowerCase().replaceAll('-', ' ').replace(/\s+/g, '_');

    return t(`pages.admin.dashboard.issue.${key}`, { value: module.issue.value ?? module.issueCount });
}

function moduleTooltip(module: DashboardModule): string {
    return t('pages.admin.dashboard.modules.issue_tooltip', {
        status: statusLabel(module.status),
        issue: moduleIssueLabel(module),
    });
}
</script>

<template>
    <Head :title="t('navigation.admin_dashboard')" />
    <AppLayout :title="t('navigation.admin_dashboard')" :title-icon="IconLayoutDashboard" mode="admin" :show-locale-switcher="true">
        <PageStack>
            <div class="grid gap-5">
                <SurfaceCard
                    :title="t('pages.admin.dashboard.release.title')"
                    :icon="IconBox"
                    tone="teal"
                    :padded="false"
                    overflow="hidden"
                >
                    <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                        <OperationalTile
                            v-for="item in releaseDetails"
                            :key="item.label"
                            :label="item.label"
                            :value="item.value"
                            :icon="item.icon"
                            :mono="item.mono"
                        />
                    </div>
                </SurfaceCard>

                <SurfaceCard
                    :title="t('pages.admin.dashboard.external.title')"
                    :icon="IconActivityHeartbeat"
                    tone="sky"
                    :padded="false"
                    overflow="hidden"
                >
                    <ul class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                        <li v-for="item in externalRows" :key="item.key" class="min-w-0">
                            <OperationalTile
                                :label="mechanismLabel(item.key)"
                                :icon="mechanismIcon(item.key)"
                                :status-label="statusLabel(item.status)"
                                :status-tone="statusTone(item.status)"
                                :status-icon="statusIcon(item.status)"
                            />
                        </li>
                    </ul>
                </SurfaceCard>

                <SurfaceCard
                    :title="t('pages.admin.dashboard.modules.title')"
                    :icon="IconServerCog"
                    tone="emerald"
                    :padded="false"
                    overflow="hidden"
                >
                    <div class="grid gap-3 border-b border-zinc-200 p-4 dark:border-zinc-800 sm:grid-cols-3">
                        <OperationalMetricTile
                            v-for="counter in moduleCounters"
                            :key="counter.label"
                            :label="counter.label"
                            :value="counter.value"
                        />
                    </div>
                    <ul class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                        <li v-for="module in moduleRows" :key="module.key" class="min-w-0">
                            <OperationalTile
                                :label="moduleLabel(module.key, t)"
                                :icon="moduleIcon(module.key)"
                                :status-label="statusLabel(module.status)"
                                :status-tone="statusTone(module.status)"
                                :status-icon="statusIcon(module.status)"
                                :tooltip="module.status === 'healthy' ? null : moduleTooltip(module)"
                            />
                        </li>
                    </ul>
                </SurfaceCard>
            </div>
        </PageStack>
    </AppLayout>
</template>
