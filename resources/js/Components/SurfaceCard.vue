<script setup lang="ts">
import type { Component } from 'vue';

import CardHeader from './CardHeader.vue';

withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        icon?: Component;
        tone?: 'teal' | 'sky' | 'emerald' | 'amber' | 'rose' | 'zinc';
        iconVariant?: 'main' | 'secondary' | 'none';
        padded?: boolean;
        bodyClass?: string;
        overflow?: 'visible' | 'hidden';
    }>(),
    {
        title: undefined,
        subtitle: undefined,
        icon: undefined,
        tone: 'teal',
        iconVariant: 'secondary',
        padded: true,
        bodyClass: '',
        overflow: 'visible',
    },
);
</script>

<template>
    <section
        class="rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
        :class="overflow === 'hidden' ? 'overflow-hidden' : 'overflow-visible'"
    >
        <div
            v-if="title || $slots.header || $slots.actions"
            class="border-b border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/60"
        >
            <slot name="header">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                    <CardHeader :title="title ?? ''" :subtitle="subtitle" :icon="icon" :tone="tone" :icon-variant="iconVariant" />
                    <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
                        <slot name="actions" />
                    </div>
                </div>
            </slot>
        </div>

        <div :class="[padded ? 'p-4' : '', bodyClass]">
            <slot />
        </div>
    </section>
</template>
