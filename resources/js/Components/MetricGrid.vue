<script setup lang="ts">
import type { Component } from 'vue';

interface MetricGridItem {
    label: string;
    value: string;
    icon: Component;
    tone?: string;
}

withDefaults(
    defineProps<{
        items: MetricGridItem[];
        columns?: string;
    }>(),
    {
        columns: 'grid gap-3 sm:grid-cols-2 xl:grid-cols-4',
    },
);

function toneClass(tone: string | undefined): string {
    const tones: Record<string, string> = {
        amber: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
        rose: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200',
        sky: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200',
        teal: 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-200',
        zinc: 'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300',
    };

    return tones[tone ?? 'teal'];
}
</script>

<template>
    <div :class="columns">
        <section
            v-for="item in items"
            :key="item.label"
            class="flex min-w-0 items-start gap-3 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
        >
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border" :class="toneClass(item.tone)">
                <component :is="item.icon" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
            </span>
            <span class="min-w-0">
                <p class="text-xs leading-4 font-semibold uppercase text-zinc-500 [overflow-wrap:anywhere] dark:text-zinc-400">
                    {{ item.label }}
                </p>
                <p class="mt-1 truncate text-sm font-medium text-zinc-950 dark:text-zinc-50">{{ item.value }}</p>
            </span>
        </section>
    </div>
</template>
