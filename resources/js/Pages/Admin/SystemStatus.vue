<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconAlertTriangle, IconCircleCheck, IconLayoutDashboard } from '@tabler/icons-vue';
import { computed } from 'vue';

import ComposableViewHost from '../../Components/ComposableView/ComposableViewHost.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { resolveComposableHostView, SYSTEM_STATUS_ELEMENTS } from '../../Services/composableViewRegistry';
import type { ComposableViewAvailability } from '../../Types/composable-view';

const props = defineProps<{
    availability: ComposableViewAvailability[];
}>();

const view = computed(() => resolveComposableHostView('admin.system-status', SYSTEM_STATUS_ELEMENTS, props.availability));
const unavailableSignals = computed(() => view.value.elements.filter((element) => element.availability.reason !== 'available'));
</script>

<template>
    <Head title="Admin dashboard" />
    <AdminLayout title="Admin dashboard" :title-icon="IconLayoutDashboard">
        <section class="space-y-4">
            <section
                class="flex flex-col gap-4 border-b border-zinc-200 pb-5 dark:border-zinc-800 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">Operations</p>
                    <h1 class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">Admin dashboard</h1>
                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                        Release identity, external readiness, and module health in one operational view.
                    </p>
                </div>
                <div
                    class="inline-flex w-fit items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold"
                    :class="
                        unavailableSignals.length === 0
                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                            : 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                    "
                >
                    <component
                        :is="unavailableSignals.length === 0 ? IconCircleCheck : IconAlertTriangle"
                        aria-hidden="true"
                        class="size-4"
                        :stroke-width="1.8"
                    />
                    <span>{{
                        unavailableSignals.length === 0 ? 'All signals available' : `${unavailableSignals.length} signals unavailable`
                    }}</span>
                </div>
            </section>

            <ComposableViewHost :view="view" />
        </section>
    </AdminLayout>
</template>
