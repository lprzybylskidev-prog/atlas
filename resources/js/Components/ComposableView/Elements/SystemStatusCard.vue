<script setup lang="ts">
import { IconAlertTriangle, IconCircleCheck, IconCircleX } from '@tabler/icons-vue';

type SystemStatusCheck = {
    key: string;
    label: string;
    status: 'healthy' | 'degraded' | 'unhealthy' | string;
    blocking: boolean;
    description: string;
};

defineProps<{
    data?: {
        label: string;
        value: string;
        description: string;
        status?: string;
        releaseVersion?: string | null;
        releaseId?: string | null;
        environment?: string | null;
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
        latestFailedModule?: string | null;
        latestFailedAt?: string | null;
        latestFailureReason?: string | null;
    } | null;
}>();

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
        <p class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ data?.label ?? 'Status' }}</p>
        <p class="mt-2 text-2xl font-semibold text-teal-700 dark:text-teal-300">{{ data?.value ?? 'Available' }}</p>
        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ data?.description ?? 'Module gate passed.' }}</p>

        <dl v-if="data?.status" class="mt-4 grid gap-2 text-xs">
            <div v-if="data.releaseVersion">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Version</dt>
                <dd class="mt-1 break-words text-zinc-800 dark:text-zinc-200">{{ data.releaseVersion }}</dd>
            </div>
            <div v-if="data.releaseId">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Release ID</dt>
                <dd class="mt-1 break-words font-mono text-zinc-800 dark:text-zinc-200">{{ data.releaseId }}</dd>
            </div>
            <div v-if="data.environment">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Environment</dt>
                <dd class="mt-1 break-words text-zinc-800 dark:text-zinc-200">{{ data.environment }}</dd>
            </div>
            <div v-if="data.deployedAt">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Last deploy</dt>
                <dd class="mt-1 break-words text-zinc-800 dark:text-zinc-200">{{ data.deployedAt }}</dd>
            </div>
            <div v-if="data.deployedBy">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Deploy operator</dt>
                <dd class="mt-1 break-words text-zinc-800 dark:text-zinc-200">{{ data.deployedBy }}</dd>
            </div>
            <div v-if="data.deploySource">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Deploy source</dt>
                <dd class="mt-1 break-words font-mono text-zinc-800 dark:text-zinc-200">{{ data.deploySource }}</dd>
            </div>
            <div v-if="data.checkedAt">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Checked at</dt>
                <dd class="mt-1 break-words text-zinc-800 dark:text-zinc-200">{{ data.checkedAt }}</dd>
            </div>
            <div v-if="data.blockingTotal !== undefined && data.blockingTotal !== null">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Blocking checks</dt>
                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">
                    {{ data.blockingFailed ?? 0 }} failed / {{ data.blockingTotal }} total
                </dd>
            </div>
            <div v-if="data.degradedTotal !== undefined && data.degradedTotal !== null">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Degraded checks</dt>
                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">
                    {{ data.degradedFailed ?? 0 }} failing / {{ data.degradedTotal }} total
                </dd>
            </div>
            <div v-if="data.lastSuccessAt">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Last success</dt>
                <dd class="mt-1 break-words text-zinc-800 dark:text-zinc-200">{{ data.lastSuccessAt }}</dd>
            </div>
            <div v-if="data.lastRuntimeMs !== undefined && data.lastRuntimeMs !== null">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Runtime</dt>
                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ data.lastRuntimeMs }} ms</dd>
            </div>
            <div v-if="data.staleAfterSeconds">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Freshness threshold</dt>
                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ data.staleAfterSeconds }} seconds</dd>
            </div>
            <div v-if="data.failedCount !== undefined && data.failedCount !== null">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Failed schedules</dt>
                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ data.failedCount }}</dd>
            </div>
            <div v-if="data.latestFailedModule">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Latest failed module</dt>
                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ data.latestFailedModule }}</dd>
            </div>
            <div v-if="data.latestFailedAt">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Latest failed at</dt>
                <dd class="mt-1 break-words text-zinc-800 dark:text-zinc-200">{{ data.latestFailedAt }}</dd>
            </div>
            <div v-if="data.latestFailureReason">
                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Latest failure</dt>
                <dd class="mt-1 break-words font-mono text-zinc-800 dark:text-zinc-200">{{ data.latestFailureReason }}</dd>
            </div>
        </dl>

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
                            {{ check.blocking ? 'Blocking' : 'Degraded' }}
                        </span>
                    </span>
                    <span class="mt-1 block text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ check.description }}</span>
                </span>
            </li>
        </ul>
    </div>
</template>
