<script setup lang="ts">
import { computed } from 'vue';

import { normalizeBarChartData } from '../Services/chartData';
import type { AtlasBarChartData } from '../Types/charts';

const props = withDefaults(
    defineProps<{
        chart: AtlasBarChartData;
        tone?: 'teal' | 'sky' | 'emerald' | 'amber' | 'rose' | 'zinc';
    }>(),
    {
        tone: 'teal',
    },
);

const normalized = computed(() => normalizeBarChartData(props.chart));
const chartTitleId = computed(() => `atlas-bar-chart-${normalized.value.title.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`);

function barClass(): string {
    const tones: Record<string, string> = {
        amber: 'fill-amber-500 dark:fill-amber-400',
        emerald: 'fill-emerald-500 dark:fill-emerald-400',
        rose: 'fill-rose-500 dark:fill-rose-400',
        sky: 'fill-sky-500 dark:fill-sky-400',
        teal: 'fill-teal-600 dark:fill-teal-400',
        zinc: 'fill-zinc-500 dark:fill-zinc-400',
    };

    return tones[props.tone];
}

function formatValue(value: number): string {
    return new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(value);
}
</script>

<template>
    <figure class="min-w-0" role="group" :aria-labelledby="chartTitleId">
        <figcaption class="mb-3 min-w-0">
            <p :id="chartTitleId" class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                {{ normalized.title }}
            </p>
            <p v-if="normalized.description" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                {{ normalized.description }}
            </p>
        </figcaption>

        <div class="space-y-3">
            <div v-for="series in normalized.series" :key="series.label" class="space-y-2">
                <p class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ series.label }}</p>
                <div
                    v-for="point in series.points"
                    :key="point.label"
                    class="grid grid-cols-[minmax(6rem,10rem)_1fr_auto] items-center gap-2"
                >
                    <span class="truncate text-xs text-zinc-600 dark:text-zinc-300">{{ point.label }}</span>
                    <svg class="h-4 w-full overflow-visible" viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true">
                        <rect class="fill-zinc-100 dark:fill-zinc-800" x="0" y="1" width="100" height="8" rx="2" />
                        <rect :class="barClass()" x="0" y="1" :width="Math.max(1, point.ratio * 100)" height="8" rx="2" />
                    </svg>
                    <span class="text-right text-xs font-medium text-zinc-800 dark:text-zinc-100">
                        {{ formatValue(point.value) }}{{ normalized.unit ? ` ${normalized.unit}` : '' }}
                    </span>
                </div>
            </div>
        </div>
    </figure>
</template>
