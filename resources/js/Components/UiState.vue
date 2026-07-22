<script setup lang="ts">
import { IconAlertCircle, IconInbox, IconLoader2, IconSearchOff } from '@tabler/icons-vue';
import { computed } from 'vue';

const props = defineProps<{
    variant: 'loading' | 'empty' | 'error' | 'no-results';
    title: string;
    description?: string;
    size?: 'default' | 'compact';
}>();

const icon = computed(() => {
    if (props.variant === 'loading') {
        return IconLoader2;
    }

    if (props.variant === 'error') {
        return IconAlertCircle;
    }

    if (props.variant === 'no-results') {
        return IconSearchOff;
    }

    return IconInbox;
});
</script>

<template>
    <section
        class="flex flex-col items-center justify-center rounded-lg border border-dashed border-zinc-300 bg-white text-center dark:border-zinc-700 dark:bg-zinc-950"
        :class="size === 'compact' ? 'min-h-24 px-4 py-4' : 'min-h-40 px-6 py-8'"
        :aria-busy="variant === 'loading'"
    >
        <component
            :is="icon"
            aria-hidden="true"
            class="text-zinc-400 dark:text-zinc-500"
            :class="[size === 'compact' ? 'h-6 w-6' : 'h-8 w-8', { 'animate-spin': variant === 'loading' }]"
            :stroke-width="1.8"
        />
        <h2 class="mt-3 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ title }}</h2>
        <p v-if="description" class="mt-1 max-w-md text-sm text-zinc-500 dark:text-zinc-400">{{ description }}</p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </section>
</template>
