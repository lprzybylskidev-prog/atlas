<script setup lang="ts">
import type { Component } from 'vue';

type IconTileTone = 'teal' | 'sky' | 'emerald' | 'amber' | 'rose' | 'zinc';
type IconTileSize = 'sm' | 'md';

withDefaults(
    defineProps<{
        icon: Component;
        tone?: IconTileTone;
        size?: IconTileSize;
    }>(),
    {
        tone: 'teal',
        size: 'md',
    },
);

const toneClass: Record<IconTileTone, string> = {
    teal: 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900/70 dark:bg-teal-950/40 dark:text-teal-200',
    sky: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/70 dark:bg-sky-950/40 dark:text-sky-200',
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-200',
    amber: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-200',
    rose: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/70 dark:bg-rose-950/40 dark:text-rose-200',
    zinc: 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200',
};

const sizeClass: Record<IconTileSize, string> = {
    sm: 'h-10 w-10',
    md: 'h-14 w-14',
};

const iconClass: Record<IconTileSize, string> = {
    sm: 'h-5 w-5',
    md: 'h-7 w-7',
};
</script>

<template>
    <div class="flex shrink-0 items-center justify-center rounded-lg border" :class="[toneClass[tone], sizeClass[size]]">
        <component :is="icon" :class="iconClass[size]" aria-hidden="true" :stroke-width="1.8" />
    </div>
</template>
