<script setup lang="ts">
import { IconLoader2 } from '@tabler/icons-vue';
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        type?: 'button' | 'submit';
        tone?: 'primary' | 'neutral' | 'danger';
        loading?: boolean;
        disabled?: boolean;
        icon?: Component;
    }>(),
    {
        type: 'button',
        tone: 'primary',
        loading: false,
        disabled: false,
        icon: undefined,
    },
);
</script>

<template>
    <button
        :type="type"
        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60"
        :class="{
            'bg-teal-700 text-white hover:bg-teal-800 dark:bg-teal-600 dark:hover:bg-teal-500': tone === 'primary',
            'border border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-50':
                tone === 'neutral',
            'bg-rose-700 text-white hover:bg-rose-800 dark:bg-rose-600 dark:hover:bg-rose-500': tone === 'danger',
        }"
        :disabled="disabled || loading"
    >
        <IconLoader2 v-if="loading" aria-hidden="true" class="h-4 w-4 animate-spin" :stroke-width="1.8" />
        <component :is="icon" v-else-if="icon" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
        <slot />
    </button>
</template>
