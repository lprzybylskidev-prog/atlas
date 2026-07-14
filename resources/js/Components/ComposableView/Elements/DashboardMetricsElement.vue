<script setup lang="ts">
import type { FunctionalComponent } from 'vue';
import { IconAlertTriangle, IconClockHour4, IconInbox, IconProgressCheck } from '@tabler/icons-vue';

interface MetricItem {
    label: string;
    value: string;
    helper: string;
    icon: 'progress' | 'inbox' | 'clock' | 'alert';
}

const icons: Record<MetricItem['icon'], FunctionalComponent> = {
    progress: IconProgressCheck,
    inbox: IconInbox,
    clock: IconClockHour4,
    alert: IconAlertTriangle,
};

defineProps<{
    data?: MetricItem[];
}>();
</script>

<template>
    <div class="grid gap-4 p-0 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="metric in data" :key="metric.label" class="p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ metric.label }}</p>
                    <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">{{ metric.value }}</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-100">
                    <component :is="icons[metric.icon]" aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                </div>
            </div>
            <p class="mt-3 text-sm leading-5 text-zinc-500 dark:text-zinc-400">{{ metric.helper }}</p>
        </article>
    </div>
</template>
