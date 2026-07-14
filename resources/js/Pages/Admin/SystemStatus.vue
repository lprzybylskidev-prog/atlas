<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { IconDatabase, IconGitBranch, IconServer, IconShieldCheck } from '@tabler/icons-vue';

import StatusPill from '../../Components/StatusPill.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import type { AtlasPageProps } from '../../Types/inertia';

const page = usePage<AtlasPageProps>();

const checks = [
    { label: 'Application runtime', value: page.props.app.release.version, icon: IconGitBranch, tone: 'success' },
    { label: 'Database', value: 'PostgreSQL', icon: IconDatabase, tone: 'success' },
    { label: 'Queue/cache', value: 'Redis', icon: IconServer, tone: 'success' },
    { label: 'Auth backend', value: 'Fortify', icon: IconShieldCheck, tone: 'info' },
] as const;
</script>

<template>
    <Head title="Admin system status" />
    <AdminLayout>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="check in checks"
                :key="check.label"
                class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ check.label }}</p>
                        <p class="mt-2 text-lg font-semibold text-zinc-950 dark:text-zinc-50">{{ check.value }}</p>
                    </div>
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-lg bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    >
                        <component :is="check.icon" aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                    </div>
                </div>
                <div class="mt-4">
                    <StatusPill :tone="check.tone">Configured</StatusPill>
                </div>
            </article>
        </section>

        <section class="mt-4 rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <h2 class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">Operational guardrails</h2>
            </div>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                <div class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-zinc-950 dark:text-zinc-50">Admin route namespace</p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Current placeholder route: admin.system-status</p>
                    </div>
                    <StatusPill tone="info">Preview</StatusPill>
                </div>
                <div class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-zinc-950 dark:text-zinc-50">Release identity</p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Release ID: {{ page.props.app.release.id }}</p>
                    </div>
                    <StatusPill tone="success">Shared</StatusPill>
                </div>
                <div class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-zinc-950 dark:text-zinc-50">Provisional access</p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                            This preview uses authentication only until authorization phases are implemented.
                        </p>
                    </div>
                    <StatusPill tone="warning">Temporary</StatusPill>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
