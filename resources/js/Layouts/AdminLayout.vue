<script setup lang="ts">
import AppLayout from './AppLayout.vue';
</script>

<template>
    <AppLayout title="System status" section="Admin">
        <div
            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
        >
            Admin UI is intentionally separated visually and functionally. Final authorization gates arrive in later identity and
            authorization phases.
        </div>
        <slot />
    </AppLayout>
</template>
