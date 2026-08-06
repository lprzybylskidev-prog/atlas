<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import { IconCircleCheck, IconCircleX } from '@tabler/icons-vue';

import { statusBadgeToneForToken, type StatusBadgeTone } from '../Utils/statusBadge';

const props = withDefaults(
    defineProps<{
        value?: boolean | string;
        label?: string;
        tone?: StatusBadgeTone;
        uppercase?: boolean;
        icon?: Component;
        trueLabel?: string;
        falseLabel?: string;
    }>(),
    {
        value: undefined,
        label: undefined,
        tone: undefined,
        uppercase: false,
        icon: undefined,
        trueLabel: 'Yes',
        falseLabel: 'No',
    },
);

const toneClass: Record<StatusBadgeTone, string> = {
    neutral: 'bg-zinc-100 text-zinc-600 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800',
    info: 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:ring-sky-900',
    success: 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-900',
    warning: 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-900',
    danger: 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:ring-rose-900',
};

const isBoolean = computed(() => typeof props.value === 'boolean');
const resolvedTone = computed(() =>
    isBoolean.value
        ? props.value === true
            ? 'success'
            : 'danger'
        : (props.tone ?? statusBadgeToneForToken(String(props.value ?? props.label ?? ''))),
);
const resolvedLabel = computed(() =>
    isBoolean.value ? (props.value === true ? props.trueLabel : props.falseLabel) : (props.label ?? String(props.value ?? '')),
);
const resolvedIcon = computed(() => (isBoolean.value ? (props.value === true ? IconCircleCheck : IconCircleX) : props.icon));
</script>

<template>
    <span
        class="inline-flex min-h-6 items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold ring-1"
        :class="[toneClass[resolvedTone], uppercase ? 'uppercase' : '']"
    >
        <component :is="resolvedIcon" v-if="resolvedIcon" aria-hidden="true" class="h-3.5 w-3.5" :stroke-width="1.8" />
        {{ resolvedLabel }}
    </span>
</template>
