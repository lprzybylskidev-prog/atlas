<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { IconClipboardList, IconFileText, IconGauge, IconLayoutDashboard, IconPuzzle, IconRotateClockwise } from '@tabler/icons-vue';
import { computed } from 'vue';
import type { Component } from 'vue';

import ComposableViewHost from '../../Components/ComposableView/ComposableViewHost.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { resolveComposableHostView, SYSTEM_STATUS_ELEMENTS } from '../../Services/composableViewRegistry';
import type { AtlasPageProps } from '../../Types/inertia';
import type { ComposableViewAvailability } from '../../Types/composable-view';

const props = defineProps<{
    availability: ComposableViewAvailability[];
}>();

const view = computed(() => resolveComposableHostView('admin.system-status', SYSTEM_STATUS_ELEMENTS, props.availability));
const page = usePage<AtlasPageProps>();

const quickLinks = computed<{ label: string; description: string; href: string; icon: Component; route?: string }[]>(() =>
    [
        {
            label: 'Application dashboard',
            description: 'Return to the regular workspace.',
            href: '/',
            icon: IconGauge,
        },
        {
            label: 'Application logs',
            description: 'Review curated runtime log entries.',
            href: '/admin/logs',
            icon: IconFileText,
            route: 'admin.logs.index',
        },
        {
            label: 'Queues',
            description: 'Inspect failed jobs and retry safely.',
            href: '/admin/queues',
            icon: IconRotateClockwise,
            route: 'admin.queues.index',
        },
        {
            label: 'Modules',
            description: 'Check deployment and team activation.',
            href: '/admin/modules',
            icon: IconPuzzle,
            route: 'admin.modules.index',
        },
        {
            label: 'Audit',
            description: 'Open operational and security audit history.',
            href: '/admin/audit',
            icon: IconClipboardList,
            route: 'admin.audit.index',
        },
    ].filter((link) => link.route === undefined || page.props.auth.availableAdminRoutes.includes(link.route)),
);
</script>

<template>
    <Head title="Admin dashboard" />
    <AdminLayout title="Admin dashboard" :title-icon="IconLayoutDashboard">
        <section class="space-y-5">
            <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 sm:p-6">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">Operations</p>
                        <h1 class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">Admin dashboard</h1>
                        <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                            Operational entry point for system readiness, release identity, scheduler health, module activation, queues,
                            logs, and audit review.
                        </p>
                    </div>
                    <Link
                        href="/"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg border border-zinc-200 px-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 hover:text-zinc-950 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900 dark:hover:text-zinc-50"
                    >
                        <IconGauge aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                        Application dashboard
                    </Link>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <Link
                        v-for="link in quickLinks"
                        :key="link.href"
                        :href="link.href"
                        class="group flex min-h-24 gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 transition hover:border-teal-200 hover:bg-teal-50 dark:border-zinc-800 dark:bg-zinc-900/50 dark:hover:border-teal-900 dark:hover:bg-teal-950/40"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-teal-200 bg-white text-teal-700 dark:border-teal-900 dark:bg-zinc-950 dark:text-teal-200"
                        >
                            <component :is="link.icon" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ link.label }}</span>
                            <span class="mt-1 block text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ link.description }}</span>
                        </span>
                    </Link>
                </div>
            </section>

            <section>
                <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-zinc-950 dark:text-zinc-50">System status</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Live operational checks loaded from the health and module foundations.
                        </p>
                    </div>
                </div>
                <ComposableViewHost :view="view" />
            </section>
        </section>
    </AdminLayout>
</template>
