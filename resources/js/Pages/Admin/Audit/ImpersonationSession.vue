<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconArrowLeft, IconUserScan } from '@tabler/icons-vue';

import AdminActionLink from '../../../Components/AdminActionLink.vue';
import DataTable from '../../../Components/DataTable.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import type { DataTableColumn } from '../../../Types/data-table';

interface AuditEventRow extends Record<string, unknown> {
    publicId: string;
    occurredAt: string;
    module: string;
    action: string;
    result: string;
    source: string;
    actorPublicId: string;
    actualActorPublicId: string;
    impersonatedUserPublicId: string;
    targetType: string;
    targetPublicId: string;
    teamPublicId: string;
    reason: string;
    security: boolean;
}

const props = defineProps<{
    session: {
        id: string;
        startedAt: string;
        endedAt: string | null;
        actualActorPublicId: string;
        impersonatedUserPublicId: string;
        teamPublicId: string;
        reason: string;
        operationCount: number;
        rejectedCount: number;
    };
    events: AuditEventRow[];
}>();

const columns: DataTableColumn<AuditEventRow>[] = [
    { key: 'occurredAt', label: 'Occurred at', format: 'datetime' },
    { key: 'module', label: 'Module' },
    { key: 'action', label: 'Action' },
    { key: 'result', label: 'Result' },
    { key: 'actorPublicId', label: 'Actor' },
    { key: 'targetType', label: 'Target type' },
    { key: 'targetPublicId', label: 'Target' },
    { key: 'teamPublicId', label: 'Team' },
    { key: 'reason', label: 'Reason' },
];
</script>

<template>
    <Head :title="`Impersonation ${session.id}`" />
    <AdminLayout title="Impersonation audit" :title-icon="IconUserScan">
        <section class="space-y-5">
            <div class="flex justify-start">
                <AdminActionLink href="/admin/audit" :icon="IconArrowLeft"> Back to audit </AdminActionLink>
            </div>

            <section
                class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-5 text-sm dark:border-zinc-800 dark:bg-zinc-950 md:grid-cols-2"
            >
                <div>
                    <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Session</p>
                    <p class="mt-1 break-all font-mono text-zinc-900 dark:text-zinc-100">{{ session.id }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Reason</p>
                    <p class="mt-1 text-zinc-900 dark:text-zinc-100">{{ session.reason }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Administrator</p>
                    <p class="mt-1 break-all font-mono text-zinc-900 dark:text-zinc-100">{{ session.actualActorPublicId }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">User</p>
                    <p class="mt-1 break-all font-mono text-zinc-900 dark:text-zinc-100">{{ session.impersonatedUserPublicId }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Started</p>
                    <p class="mt-1 text-zinc-900 dark:text-zinc-100">{{ session.startedAt }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Ended</p>
                    <p class="mt-1 text-zinc-900 dark:text-zinc-100">{{ session.endedAt ?? 'Active or not recorded' }}</p>
                </div>
            </section>

            <DataTable title="Session events" :rows="props.events" :columns="columns" row-key="publicId" />
        </section>
    </AdminLayout>
</template>
