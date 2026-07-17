<script setup lang="ts">
import { IconAlertTriangle, IconCircleCheck, IconCircleX, IconInfoCircle } from '@tabler/icons-vue';
import { computed } from 'vue';

const props = defineProps<{
    value: string;
}>();

const normalized = computed(() => props.value.toLowerCase().trim());
const label = computed(() =>
    normalized.value
        .split(/[-_\s]+/u)
        .filter(Boolean)
        .map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`)
        .join(' '),
);
const icon = computed(() => {
    if (['success', 'ok', 'resolved'].includes(normalized.value)) {
        return IconCircleCheck;
    }

    if (['warning', 'warn'].includes(normalized.value)) {
        return IconAlertTriangle;
    }

    if (['error', 'danger', 'failed', 'failure'].includes(normalized.value)) {
        return IconCircleX;
    }

    return IconInfoCircle;
});
const badgeClass = computed(() => {
    if (['success', 'ok', 'resolved'].includes(normalized.value)) {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-900';
    }

    if (['warning', 'warn'].includes(normalized.value)) {
        return 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-900';
    }

    if (['error', 'danger', 'failed', 'failure'].includes(normalized.value)) {
        return 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:ring-rose-900';
    }

    return 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:ring-sky-900';
});
</script>

<template>
    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-medium ring-1" :class="badgeClass">
        <component :is="icon" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
        {{ label || 'Info' }}
    </span>
</template>
