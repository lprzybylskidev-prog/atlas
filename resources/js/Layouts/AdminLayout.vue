<script setup lang="ts">
import type { Component } from 'vue';

import AppLayout from './AppLayout.vue';

withDefaults(
    defineProps<{
        title?: string;
        titleIcon?: Component;
    }>(),
    {
        title: 'Dashboard',
        titleIcon: undefined,
    },
);
</script>

<template>
    <AppLayout :title="title" :title-icon="titleIcon" mode="admin" ui-locale="en">
        <slot />
    </AppLayout>
</template>
