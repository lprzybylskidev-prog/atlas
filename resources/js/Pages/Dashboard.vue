<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import ComposableViewHost from '../Components/ComposableView/ComposableViewHost.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import { resolveComposableHostView } from '../Services/composableViewRegistry';
import { foundationDashboardElements } from '../Services/foundationDashboardElements';

const view = resolveComposableHostView('app.dashboard', foundationDashboardElements);
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout title="Dashboard operacyjny">
        <ComposableViewHost :view="view" />
    </AppLayout>
</template>
