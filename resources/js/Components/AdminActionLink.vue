<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        href: string;
        icon?: Component;
        tone?: 'primary' | 'neutral';
    }>(),
    {
        icon: undefined,
        tone: 'neutral',
    },
);
</script>

<template>
    <Link
        :href="href"
        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium transition focus-visible:outline focus-visible:outline-amber-500"
        :class="{
            'bg-teal-700 text-white hover:bg-teal-800 dark:bg-teal-600 dark:hover:bg-teal-500': tone === 'primary',
            'border border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-50':
                tone === 'neutral',
        }"
    >
        <component :is="icon" v-if="icon" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
        <slot />
    </Link>
</template>
