<script setup lang="ts">
import { IconAlertTriangle, IconCircleCheck, IconCircleDashed, IconCircleX, IconInfoCircle } from '@tabler/icons-vue';

import { useTranslator } from '../../../Localization/translator';
import { moduleLabel } from '../../../Utils/moduleLabels';

type ModuleIssue = {
    severity: 'healthy' | 'degraded' | 'unhealthy' | 'info' | string;
    label: string;
    description: string;
    value?: number | string | null;
};

type ModuleStatusRow = {
    key: string;
    category: string;
    status: 'healthy' | 'degraded' | 'unhealthy' | 'inactive' | 'unavailable' | string;
    technicallyAvailable: boolean;
    globallyEnabled: boolean;
    teamEnabled: boolean;
    effectiveEnabled: boolean;
    source: string;
    requiredDependencies: string[];
    optionalDependencies: string[];
    issues: ModuleIssue[];
};

defineProps<{
    data?: {
        label: string;
        value: string;
        description: string;
        status?: string;
        activeCount?: number | null;
        moduleCount?: number | null;
        attentionCount?: number | null;
        modules?: ModuleStatusRow[];
    } | null;
}>();

const { t } = useTranslator();

const statusClass = (status: string): string => {
    if (status === 'healthy') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'inactive') {
        return 'border-zinc-300 bg-zinc-100 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300';
    }

    if (status === 'degraded' || status === 'info') {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300';
};

const statusIcon = (status: string) => {
    if (status === 'healthy') {
        return IconCircleCheck;
    }

    if (status === 'inactive') {
        return IconCircleDashed;
    }

    if (status === 'degraded' || status === 'info') {
        return IconAlertTriangle;
    }

    return IconCircleX;
};

const issueIcon = (severity: string) => {
    if (severity === 'info') {
        return IconInfoCircle;
    }

    return statusIcon(severity);
};
</script>

<template>
    <div class="p-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex items-start gap-3">
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border"
                        :class="statusClass(data?.status ?? 'healthy')"
                        aria-hidden="true"
                    >
                        <component :is="statusIcon(data?.status ?? 'healthy')" class="h-5 w-5" :stroke-width="1.8" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ data?.label ?? 'Modules' }}</span>
                        <span class="mt-1 block text-lg font-semibold text-teal-700 dark:text-teal-300">{{
                            data?.value ?? 'Available'
                        }}</span>
                    </span>
                </div>
                <p class="mt-3 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                    {{ data?.description ?? 'Module registry is available.' }}
                </p>
            </div>

            <dl class="grid min-w-60 grid-cols-3 gap-2 text-xs">
                <div class="rounded-md bg-zinc-50 p-2 dark:bg-zinc-900/70">
                    <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Active</dt>
                    <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ data?.activeCount ?? 0 }}</dd>
                </div>
                <div class="rounded-md bg-zinc-50 p-2 dark:bg-zinc-900/70">
                    <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Total</dt>
                    <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ data?.moduleCount ?? 0 }}</dd>
                </div>
                <div class="rounded-md bg-zinc-50 p-2 dark:bg-zinc-900/70">
                    <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Attention</dt>
                    <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ data?.attentionCount ?? 0 }}</dd>
                </div>
            </dl>
        </div>

        <div class="mt-4 overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-800">
            <div
                v-for="module in data?.modules ?? []"
                :key="module.key"
                class="grid gap-3 border-b border-zinc-200 p-3 last:border-b-0 dark:border-zinc-800 xl:grid-cols-[13rem_minmax(0,1fr)_12rem]"
            >
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-full border"
                            :class="statusClass(module.status)"
                        >
                            <component :is="statusIcon(module.status)" class="size-4" :stroke-width="1.8" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ moduleLabel(module.key, t) }}</p>
                            <p class="text-xs uppercase text-zinc-500 dark:text-zinc-400">{{ module.category }}</p>
                        </div>
                    </div>
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded bg-zinc-100 px-2 py-1 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                            {{ t('pages.admin.modules.effective') }}:
                            {{ module.effectiveEnabled ? t('datatable.boolean.yes') : t('datatable.boolean.no') }}
                        </span>
                        <span class="rounded bg-zinc-100 px-2 py-1 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                            {{ t('pages.admin.modules.global') }}:
                            {{ module.globallyEnabled ? t('pages.admin.modules.enabled') : t('pages.admin.modules.disabled') }}
                        </span>
                        <span class="rounded bg-zinc-100 px-2 py-1 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                            {{ t('pages.admin.modules.team') }}:
                            {{ module.teamEnabled ? t('pages.admin.modules.enabled') : t('pages.admin.modules.disabled') }}
                        </span>
                        <span class="rounded bg-zinc-100 px-2 py-1 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                            {{ t('pages.admin.modules.team_source') }}: {{ module.source }}
                        </span>
                    </div>

                    <ul v-if="module.issues.length > 0" class="mt-3 grid gap-2">
                        <li v-for="issue in module.issues" :key="`${module.key}-${issue.label}`" class="flex gap-2 text-xs">
                            <component
                                :is="issueIcon(issue.severity)"
                                class="mt-0.5 size-4 shrink-0"
                                :class="statusClass(issue.severity)"
                                :stroke-width="1.8"
                            />
                            <span class="min-w-0 text-zinc-600 dark:text-zinc-300">
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ issue.label
                                    }}<template v-if="issue.value !== undefined && issue.value !== null">: {{ issue.value }}</template>
                                </span>
                                <span class="block leading-5">{{ issue.description }}</span>
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">No module-specific issues are waiting for review.</p>
                </div>

                <div class="min-w-0 text-xs text-zinc-500 dark:text-zinc-400">
                    <p>Technical: {{ module.technicallyAvailable ? 'available' : 'unavailable' }}</p>
                    <p v-if="module.requiredDependencies.length > 0" class="mt-1 break-words">
                        Required: {{ module.requiredDependencies.join(', ') }}
                    </p>
                    <p v-if="module.optionalDependencies.length > 0" class="mt-1 break-words">
                        Optional: {{ module.optionalDependencies.join(', ') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
