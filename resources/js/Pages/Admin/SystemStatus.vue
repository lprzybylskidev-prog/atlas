<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import ComposableViewHost from '../../Components/ComposableView/ComposableViewHost.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { resolveComposableHostView, SYSTEM_STATUS_ELEMENTS } from '../../Services/composableViewRegistry';
import type { ComposableViewAvailability } from '../../Types/composable-view';

const props = defineProps<{
    availability: ComposableViewAvailability[];
}>();

const view = computed(() => resolveComposableHostView('admin.system-status', SYSTEM_STATUS_ELEMENTS, props.availability));
</script>

<template>
    <Head title="Admin dashboard" />
    <AdminLayout title="Dashboard">
        <ComposableViewHost :view="view" />
    </AdminLayout>
</template>
