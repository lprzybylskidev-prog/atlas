<script setup lang="ts">
import type { Component } from 'vue';

import TextBadge from './TextBadge.vue';
import Tooltip from './Tooltip.vue';

type TextBadgeTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

withDefaults(
    defineProps<{
        label: string;
        icon: Component;
        value?: string | number | null;
        mono?: boolean;
        statusLabel?: string;
        statusTone?: TextBadgeTone;
        statusIcon?: Component;
        tooltip?: string | null;
    }>(),
    {
        value: undefined,
        mono: false,
        statusLabel: undefined,
        statusTone: 'neutral',
        statusIcon: undefined,
        tooltip: null,
    },
);
</script>

<template>
    <Tooltip :text="tooltip ?? ''" :disabled="!tooltip" full-width placement="top" align="start" wide>
        <div
            class="flex w-full min-w-0 items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/50"
        >
            <div class="flex min-w-0 items-center gap-3">
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-lg bg-white text-zinc-600 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-950 dark:text-zinc-300 dark:ring-zinc-800"
                >
                    <component :is="icon" aria-hidden="true" class="size-5" :stroke-width="1.8" />
                </span>
                <div class="min-w-0">
                    <p v-if="value === undefined" class="truncate text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                        {{ label }}
                    </p>
                    <template v-else>
                        <p class="truncate text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ label }}</p>
                        <p
                            class="mt-1 min-w-0 truncate text-sm font-semibold text-zinc-950 dark:text-zinc-50"
                            :class="{ 'font-mono text-xs': mono }"
                        >
                            {{ value }}
                        </p>
                    </template>
                </div>
            </div>

            <TextBadge v-if="statusLabel" class="ml-auto shrink-0" :label="statusLabel" :tone="statusTone" :icon="statusIcon" />
        </div>
    </Tooltip>
</template>
