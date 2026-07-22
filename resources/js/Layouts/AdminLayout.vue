<script setup lang="ts">
import type { Component } from 'vue';

import AppLayout from './AppLayout.vue';
import type { ShellSubnavigationItem } from '../Types/navigation';

withDefaults(
    defineProps<{
        title?: string;
        titleIcon?: Component;
        subnavigation?: ShellSubnavigationItem[];
        subnavigationLabel?: string;
    }>(),
    {
        title: 'Dashboard',
        titleIcon: undefined,
        subnavigation: () => [],
        subnavigationLabel: 'Admin section navigation',
    },
);
</script>

<template>
    <AppLayout
        :title="title"
        :title-icon="titleIcon"
        mode="admin"
        :show-locale-switcher="false"
        ui-locale="en"
        :subnavigation="subnavigation"
        :subnavigation-label="subnavigationLabel"
    >
        <slot />
    </AppLayout>
</template>
