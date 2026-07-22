<script setup lang="ts">
import type { Component } from 'vue';

import CardHeader from './CardHeader.vue';

withDefaults(
    defineProps<{
        title: string;
        icon: Component;
        subtitle?: string;
    }>(),
    {
        subtitle: undefined,
    },
);
</script>

<template>
    <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
        <CardHeader :title="title" :subtitle="subtitle" :icon="icon" tone="zinc" icon-variant="secondary" />
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
