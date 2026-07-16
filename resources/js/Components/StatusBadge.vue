<script setup lang="ts">
import { IconCircleCheck, IconCircleX } from '@tabler/icons-vue';

withDefaults(
    defineProps<{
        value: boolean;
        trueLabel?: string;
        falseLabel?: string;
    }>(),
    {
        trueLabel: 'Yes',
        falseLabel: 'No',
    },
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-medium"
        :class="
            value
                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-900'
                : 'bg-rose-50 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:ring-rose-900'
        "
    >
        <IconCircleCheck v-if="value" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
        <IconCircleX v-else aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
        {{ value ? trueLabel : falseLabel }}
    </span>
</template>
