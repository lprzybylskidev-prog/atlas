<script setup lang="ts">
import type { Component } from 'vue';

type OperationalMetricTileTone = 'teal' | 'sky' | 'emerald' | 'amber' | 'rose' | 'zinc';

withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        icon?: Component;
        tone?: OperationalMetricTileTone;
    }>(),
    {
        icon: undefined,
        tone: 'zinc',
    },
);

const toneClass: Record<OperationalMetricTileTone, string> = {
    teal: 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900/70 dark:bg-teal-950/40 dark:text-teal-200',
    sky: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/70 dark:bg-sky-950/40 dark:text-sky-200',
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-200',
    amber: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-200',
    rose: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/70 dark:bg-rose-950/40 dark:text-rose-200',
    zinc: 'border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200',
};
</script>

<template>
    <div class="min-w-0 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/50">
        <p class="truncate text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ label }}</p>
        <div class="mt-2 flex min-w-0 items-center gap-2">
            <span v-if="icon" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border" :class="toneClass[tone]">
                <component :is="icon" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
            </span>
            <p class="min-w-0 truncate text-lg font-semibold text-zinc-950 dark:text-zinc-50">{{ value }}</p>
        </div>
    </div>
</template>
