<script setup lang="ts">
import { IconServerCog } from '@tabler/icons-vue';

import AppLayout from '../../Layouts/AppLayout.vue';

defineProps<{
    title: string;
    currentPath: string;
}>();
</script>

<template>
    <AppLayout mode="admin" :title="title" :title-icon="IconServerCog" navigation-section="managed-processes">
        <slot />
    </AppLayout>
</template>
