<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconAlertTriangle, IconCircleCheck, IconLayoutDashboard } from '@tabler/icons-vue';
import { computed } from 'vue';

import ComposableViewHost from '../../Components/ComposableView/ComposableViewHost.vue';
import PageStack from '../../Components/PageStack.vue';
import TextBadge from '../../Components/TextBadge.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { resolveComposableHostView, SYSTEM_STATUS_ELEMENTS } from '../../Services/composableViewRegistry';
import type { ComposableViewAvailability } from '../../Types/composable-view';

const props = defineProps<{
    availability: ComposableViewAvailability[];
}>();

const view = computed(() => resolveComposableHostView('admin.system-status', SYSTEM_STATUS_ELEMENTS, props.availability));
const unavailableSignals = computed(() => view.value.elements.filter((element) => element.availability.reason !== 'available'));
const statusBadge = computed(() => {
    if (unavailableSignals.value.length === 0) {
        return {
            label: 'All signals available',
            tone: 'success' as const,
            icon: IconCircleCheck,
        };
    }

    return {
        label: `${unavailableSignals.value.length} signals unavailable`,
        tone: 'warning' as const,
        icon: IconAlertTriangle,
    };
});
</script>

<template>
    <Head title="Admin dashboard" />
    <AdminLayout title="Admin dashboard" :title-icon="IconLayoutDashboard">
        <PageStack>
            <section
                class="flex flex-col gap-4 border-b border-zinc-200 pb-5 dark:border-zinc-800 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">Operations</p>
                    <h1 class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">Admin dashboard</h1>
                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                        Release identity, external readiness, and module health in one operational view.
                    </p>
                </div>
                <TextBadge :label="statusBadge.label" :tone="statusBadge.tone" :icon="statusBadge.icon" />
            </section>

            <ComposableViewHost :view="view" />
        </PageStack>
    </AdminLayout>
</template>
