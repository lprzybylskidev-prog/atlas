<script setup lang="ts">
import { IconAlertTriangle, IconCircleCheck, IconCircleX, IconInfoCircle } from '@tabler/icons-vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        title?: string;
        tone?: 'info' | 'success' | 'warning' | 'danger';
        role?: 'alert' | 'status' | 'note';
    }>(),
    {
        title: undefined,
        tone: 'info',
        role: 'note',
    },
);

const icon = computed(() => {
    if (props.tone === 'success') {
        return IconCircleCheck;
    }

    if (props.tone === 'danger') {
        return IconCircleX;
    }

    if (props.tone === 'warning') {
        return IconAlertTriangle;
    }

    return IconInfoCircle;
});

const toneClass = computed(() => {
    if (props.tone === 'success') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100';
    }

    if (props.tone === 'danger') {
        return 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100';
    }

    if (props.tone === 'warning') {
        return 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100';
    }

    return 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100';
});
</script>

<template>
    <section class="rounded-lg border p-4 text-sm" :class="toneClass" :role="role">
        <div class="flex gap-3">
            <component :is="icon" aria-hidden="true" class="mt-0.5 h-5 w-5 shrink-0" :stroke-width="1.8" />
            <div class="min-w-0">
                <p v-if="title" class="font-medium">{{ title }}</p>
                <div :class="title ? 'mt-1 leading-6' : 'leading-6'">
                    <slot />
                </div>
                <div v-if="$slots.actions" class="mt-4">
                    <slot name="actions" />
                </div>
            </div>
        </div>
    </section>
</template>
