<script setup lang="ts">
import { IconAlertTriangle, IconCircleCheck, IconCircleX } from '@tabler/icons-vue';
import { computed } from 'vue';

import { useTranslator } from '../../../Localization/translator';
import { moduleLabel } from '../../../Utils/moduleLabels';

type SystemStatusCheck = {
    key: string;
    label: string;
    status: 'healthy' | 'degraded' | 'unhealthy' | string;
    blocking: boolean;
    description: string;
};

type SystemStatusItem = {
    publicId?: string | null;
    module?: string | null;
    scope?: string | null;
    targetEnabled?: boolean | null;
    effectiveAt?: string | null;
    status?: string | null;
    failureReason?: string | null;
};

const props = defineProps<{
    data?: {
        label: string;
        value: string;
        description: string;
        status?: string;
        releaseVersion?: string | null;
        releaseId?: string | null;
        environment?: string | null;
        laravelVersion?: string | null;
        phpVersion?: string | null;
        timezone?: string | null;
        runtime?: string | null;
        deployedAt?: string | null;
        deployedBy?: string | null;
        deploySource?: string | null;
        checkedAt?: string | null;
        blockingFailed?: number | null;
        blockingTotal?: number | null;
        degradedFailed?: number | null;
        degradedTotal?: number | null;
        checks?: SystemStatusCheck[];
        lastSuccessAt?: string | null;
        lastRuntimeMs?: number | null;
        staleAfterSeconds?: number | null;
        failedCount?: number | null;
        scheduledCount?: number | null;
        queueCount?: number | null;
        latestFailedAt?: string | null;
        latestFailedModule?: string | null;
        latestFailureReason?: string | null;
        items?: SystemStatusItem[];
    } | null;
}>();

const { t } = useTranslator();
const normalizedStatus = computed(() => props.data?.status ?? 'healthy');
const detailItems = computed(() =>
    [
        { label: 'Version', value: props.data?.releaseVersion, mono: false },
        { label: 'Release ID', value: props.data?.releaseId, mono: true },
        { label: 'Environment', value: props.data?.environment, mono: false },
        { label: 'Laravel', value: props.data?.laravelVersion, mono: false },
        { label: 'PHP', value: props.data?.phpVersion, mono: false },
        { label: 'Timezone', value: props.data?.timezone, mono: false },
        { label: 'Runtime', value: props.data?.runtime, mono: false },
        { label: 'Last deploy', value: props.data?.deployedAt, mono: false },
        { label: 'Deploy operator', value: props.data?.deployedBy, mono: false },
        { label: 'Deploy source', value: props.data?.deploySource, mono: true },
        { label: 'Checked at', value: props.data?.checkedAt, mono: false },
        {
            label: t('pages.admin.dashboard.readiness.blocking_checks'),
            value:
                props.data?.blockingTotal === undefined || props.data.blockingTotal === null
                    ? null
                    : t('pages.admin.dashboard.readiness.failed_out_of_total', {
                          failed: props.data.blockingFailed ?? 0,
                          total: props.data.blockingTotal,
                      }),
            mono: false,
        },
        {
            label: t('pages.admin.dashboard.readiness.warning_checks'),
            value:
                props.data?.degradedTotal === undefined || props.data.degradedTotal === null
                    ? null
                    : t('pages.admin.dashboard.readiness.warning_out_of_total', {
                          failed: props.data.degradedFailed ?? 0,
                          total: props.data.degradedTotal,
                      }),
            mono: false,
        },
        { label: 'Last success', value: props.data?.lastSuccessAt, mono: false },
        {
            label: 'Runtime',
            value: props.data?.lastRuntimeMs === undefined || props.data.lastRuntimeMs === null ? null : `${props.data.lastRuntimeMs} ms`,
            mono: false,
        },
        {
            label: 'Freshness threshold',
            value: props.data?.staleAfterSeconds ? `${props.data.staleAfterSeconds} seconds` : null,
            mono: false,
        },
        {
            label: 'Failed schedules',
            value: props.data?.failedCount === undefined || props.data.failedCount === null ? null : String(props.data.failedCount),
            mono: false,
        },
        {
            label: 'Scheduled changes',
            value:
                props.data?.scheduledCount === undefined || props.data.scheduledCount === null ? null : String(props.data.scheduledCount),
            mono: false,
        },
        {
            label: 'Queues',
            value: props.data?.queueCount === undefined || props.data.queueCount === null ? null : String(props.data.queueCount),
            mono: false,
        },
        {
            label: 'Latest failed module',
            value: props.data?.latestFailedModule ? moduleLabel(props.data.latestFailedModule, t) : null,
            mono: false,
        },
        { label: 'Latest failed at', value: props.data?.latestFailedAt, mono: false },
        { label: 'Latest failure', value: props.data?.latestFailureReason, mono: true },
    ].filter((item): item is { label: string; value: string; mono: boolean } => typeof item.value === 'string' && item.value !== ''),
);

const moduleActivationItems = computed(() => props.data?.items ?? []);

const statusClass = (status: string): string => {
    if (status === 'healthy') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'degraded') {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300';
};

const statusIcon = (status: string) => {
    if (status === 'healthy') {
        return IconCircleCheck;
    }

    if (status === 'degraded') {
        return IconAlertTriangle;
    }

    return IconCircleX;
};
</script>

<template>
    <div class="p-4">
        <div class="flex items-start gap-3">
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border"
                :class="statusClass(normalizedStatus)"
                aria-hidden="true"
            >
                <component :is="statusIcon(normalizedStatus)" class="h-5 w-5" :stroke-width="1.8" />
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ data?.label ?? 'Status' }}</span>
                <span class="mt-1 block text-lg font-semibold text-teal-700 dark:text-teal-300">{{ data?.value ?? 'Available' }}</span>
            </span>
        </div>
        <p class="mt-3 text-sm leading-6 text-zinc-500 dark:text-zinc-400">{{ data?.description ?? 'Module gate passed.' }}</p>

        <dl v-if="detailItems.length > 0" class="mt-4 grid gap-2 text-xs sm:grid-cols-2">
            <div v-for="item in detailItems" :key="item.label" class="min-w-0 rounded-md bg-zinc-50 p-2 dark:bg-zinc-900/70">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ item.label }}</dt>
                <dd class="mt-1 break-words text-zinc-800 dark:text-zinc-200" :class="{ 'font-mono': item.mono }">
                    {{ item.value }}
                </dd>
            </div>
        </dl>

        <ul v-if="moduleActivationItems.length > 0" class="mt-4 grid gap-2">
            <li
                v-for="item in moduleActivationItems"
                :key="item.publicId ?? `${item.module}-${item.effectiveAt}`"
                class="rounded-md border border-zinc-200 p-3 text-xs dark:border-zinc-800"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{
                        item.module ? moduleLabel(item.module, t) : 'Module'
                    }}</span>
                    <span class="rounded bg-zinc-100 px-2 py-0.5 font-semibold uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                        {{ item.status ?? 'scheduled' }}
                    </span>
                </div>
                <p class="mt-1 text-zinc-500 dark:text-zinc-400">
                    {{ item.targetEnabled ? 'Enable' : 'Disable' }} {{ item.scope ?? 'scope' }} at
                    {{ item.effectiveAt ?? 'scheduled time' }}
                </p>
                <p v-if="item.failureReason" class="mt-2 break-words font-mono text-rose-700 dark:text-rose-300">
                    {{ item.failureReason }}
                </p>
            </li>
        </ul>

        <ul v-if="data?.checks?.length" class="mt-4 grid gap-2">
            <li
                v-for="check in data.checks"
                :key="check.key"
                class="flex gap-3 rounded-md border border-zinc-200 p-3 text-sm dark:border-zinc-800"
            >
                <span
                    class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border"
                    :class="statusClass(check.status)"
                    aria-hidden="true"
                >
                    <component :is="statusIcon(check.status)" class="size-4" />
                </span>
                <span class="min-w-0">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ check.label }}</span>
                        <span class="text-xs uppercase text-zinc-500 dark:text-zinc-400">
                            {{
                                check.blocking
                                    ? t('pages.admin.dashboard.readiness.check_type_blocking')
                                    : t('pages.admin.dashboard.status.degraded')
                            }}
                        </span>
                    </span>
                    <span class="mt-1 block text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ check.description }}</span>
                </span>
            </li>
        </ul>
    </div>
</template>
