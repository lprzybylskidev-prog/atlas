<script setup lang="ts">
import AppLayout from './AppLayout.vue';
</script>

<template>
    <AppLayout title="Dashboard" mode="admin" :show-locale-switcher="false" ui-locale="en">
        <slot />
    </AppLayout>
</template>
