<script setup lang="ts">
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        title: string;
        icon?: Component;
        subtitle?: string;
        tone?: string;
        size?: 'sm' | 'md';
    }>(),
    {
        icon: undefined,
        subtitle: undefined,
        tone: 'teal',
        size: 'md',
    },
);
</script>

<template>
    <div class="min-w-0">
        <h2 class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ title }}</h2>
        <p v-if="subtitle" class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ subtitle }}</p>
    </div>
</template>
