<script setup lang="ts">
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        title: string;
        icon?: Component;
        subtitle?: string;
        tone?: 'teal' | 'sky' | 'emerald' | 'amber' | 'rose' | 'zinc';
        size?: 'sm' | 'md';
        iconVariant?: 'main' | 'secondary' | 'none';
        titleId?: string;
    }>(),
    {
        icon: undefined,
        subtitle: undefined,
        tone: 'teal',
        size: 'md',
        iconVariant: 'secondary',
        titleId: undefined,
    },
);

function iconToneClass(tone: string): string {
    const tones: Record<string, string> = {
        amber: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
        rose: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200',
        sky: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200',
        teal: 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-200',
        zinc: 'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300',
    };

    return tones[tone] ?? tones.teal;
}
</script>

<template>
    <div class="flex min-w-0 gap-3" :class="subtitle ? 'items-start' : 'items-center'">
        <span
            v-if="icon && iconVariant !== 'none'"
            class="flex shrink-0 items-center justify-center rounded-lg border"
            :class="[iconToneClass(tone), iconVariant === 'main' ? 'h-11 w-11' : 'h-9 w-9']"
        >
            <component :is="icon" aria-hidden="true" :class="iconVariant === 'main' ? 'h-5 w-5' : 'h-4 w-4'" :stroke-width="1.8" />
        </span>
        <div class="min-w-0">
            <h2 :id="titleId" :class="size === 'sm' ? 'text-sm' : 'text-base'" class="font-semibold text-zinc-950 dark:text-zinc-50">
                {{ title }}
            </h2>
            <p v-if="subtitle" class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ subtitle }}</p>
        </div>
    </div>
</template>
