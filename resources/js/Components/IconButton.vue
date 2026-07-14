<script setup lang="ts">
import type { FunctionalComponent } from 'vue';

defineProps<{
    label: string;
    icon: FunctionalComponent;
    active?: boolean;
}>();
</script>

<template>
    <button
        type="button"
        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-600 transition hover:border-teal-200 hover:bg-teal-50 hover:text-teal-800 focus-visible:outline focus-visible:outline-amber-500 disabled:pointer-events-none disabled:opacity-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-teal-700 dark:hover:bg-teal-950/50 dark:hover:text-teal-100"
        :class="{ 'border-teal-300 bg-teal-50 text-teal-800 dark:border-teal-600 dark:bg-teal-950 dark:text-teal-100': active }"
        :aria-label="label"
        :aria-pressed="active"
    >
        <component :is="icon" aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
    </button>
</template>
