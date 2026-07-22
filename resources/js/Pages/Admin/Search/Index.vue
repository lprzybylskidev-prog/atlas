<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { IconAlertTriangle, IconDatabaseSearch, IconRotateClockwise, IconSearch, IconShieldCheck } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed } from 'vue';

import SurfaceCard from '../../../Components/SurfaceCard.vue';
import DataTable from '../../../Components/DataTable.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
import PageStack from '../../../Components/PageStack.vue';
import SeverityBadge from '../../../Components/SeverityBadge.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import type { DataTableAction, DataTableColumn, DataTableExportMeta } from '../../../Types/data-table';

interface SearchIndex extends Record<string, unknown> {
    key: string;
    moduleKey: string;
    stableAlias: string;
    searchableFields: string[];
    filterableFields: string[];
    sortableFields: string[];
    containsSensitiveData: boolean;
    supportsDeletion: boolean;
    supportsAnonymization: boolean;
}

interface SearchReadiness {
    status: string;
    blocking: boolean;
    description: string;
}

interface RebuildRun extends Record<string, unknown> {
    publicId: string;
    status: string;
    currentStage: string | null;
    progressCurrent: number;
    progressTotal: number | null;
    progressLabel: string | null;
    createdAt: string | null;
    startedAt: string | null;
    finishedAt: string | null;
}

const props = defineProps<{
    indexes: SearchIndex[];
    summary: { indexes: number; sensitive: number; recentRebuilds: number; activeRebuilds: number };
    readiness: SearchReadiness;
    recentRebuilds: RebuildRun[];
    rebuildConfirmation: string;
    exports: DataTableExportMeta;
}>();

const form = useForm({
    confirmation: '',
    module_key: '',
    index_key: '',
});

const moduleOptions = computed(() => [
    { value: '', label: 'All modules' },
    ...Array.from(new Set(props.indexes.map((index) => index.moduleKey)))
        .sort()
        .map((value) => ({ value, label: value })),
]);
const indexOptions = computed(() => [
    { value: '', label: 'All indexes' },
    ...props.indexes.map((index) => ({ value: index.key, label: index.key })),
]);
const summaryItems = computed<{ label: string; value: string; icon: Component; tone: string }[]>(() => [
    { label: 'Registered indexes', value: String(props.summary.indexes), icon: IconDatabaseSearch, tone: 'teal' },
    {
        label: 'Sensitive indexes',
        value: String(props.summary.sensitive),
        icon: IconShieldCheck,
        tone: props.summary.sensitive > 0 ? 'amber' : 'emerald',
    },
    { label: 'Recent rebuilds', value: String(props.summary.recentRebuilds), icon: IconRotateClockwise, tone: 'sky' },
    {
        label: 'Active rebuilds',
        value: String(props.summary.activeRebuilds),
        icon: IconAlertTriangle,
        tone: props.summary.activeRebuilds > 0 ? 'amber' : 'emerald',
    },
]);

const indexColumns: DataTableColumn<SearchIndex>[] = [
    { key: 'key', label: 'Index key' },
    { key: 'moduleKey', label: 'Module' },
    { key: 'stableAlias', label: 'Stable alias' },
    { key: 'searchableFields', label: 'Searchable', format: 'list' },
    { key: 'filterableFields', label: 'Filterable', format: 'list', hidden: true },
    { key: 'sortableFields', label: 'Sortable', format: 'list', hidden: true },
    { key: 'containsSensitiveData', label: 'Sensitive', format: 'boolean' },
    { key: 'supportsDeletion', label: 'Deletion', format: 'boolean' },
    { key: 'supportsAnonymization', label: 'Anonymization', format: 'boolean' },
];
const rebuildColumns: DataTableColumn<RebuildRun>[] = [
    { key: 'status', label: 'Status', format: 'severity' },
    { key: 'currentStage', label: 'Stage' },
    { key: 'progressLabel', label: 'Progress' },
    { key: 'progressCurrent', label: 'Done', format: 'number' },
    { key: 'progressTotal', label: 'Total', format: 'number' },
    { key: 'createdAt', label: 'Created', format: 'datetime' },
    { key: 'startedAt', label: 'Started', format: 'datetime' },
    { key: 'finishedAt', label: 'Finished', format: 'datetime' },
];
const rebuildActions: DataTableAction<RebuildRun>[] = [
    { key: 'open', label: 'Open logs', href: (run) => `/admin/managed-processes/${run.publicId}`, tone: 'info' },
];

function readinessSeverity(status: string): string {
    if (status === 'healthy') {
        return 'success';
    }

    if (status === 'unhealthy') {
        return 'failed';
    }

    return 'warning';
}

function startRebuild(): void {
    form.post('/admin/search/rebuild', { preserveScroll: true });
}
</script>

<template>
    <Head title="Search" />
    <AdminLayout title="Search" :title-icon="IconSearch">
        <PageStack>
            <MetricGrid :items="summaryItems" />

            <SurfaceCard title="Meilisearch readiness" :icon="IconSearch" :subtitle="readiness.description">
                <template #actions>
                    <SeverityBadge
                        :value="readinessSeverity(readiness.status)"
                        :label="readiness.blocking ? `${readiness.status} blocking` : readiness.status"
                    />
                </template>
            </SurfaceCard>

            <SurfaceCard
                title="Start rebuild"
                :icon="IconRotateClockwise"
                subtitle="Rebuilds are queued and visible in managed process logs."
            >
                <AtlasForm class="space-y-4" :processing="form.processing" @submit="startRebuild">
                    <div class="grid gap-4 md:grid-cols-3">
                        <FormSelect v-model="form.module_key" label="Module" :options="moduleOptions" />
                        <FormSelect v-model="form.index_key" label="Index" :options="indexOptions" />
                        <FormInput
                            v-model="form.confirmation"
                            label="Confirmation"
                            :placeholder="rebuildConfirmation"
                            :error="form.errors.confirmation"
                            monospace
                            autocomplete="off"
                        />
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <FormButton
                            type="submit"
                            :icon="IconRotateClockwise"
                            :loading="form.processing"
                            :disabled="form.confirmation !== rebuildConfirmation"
                        >
                            Start rebuild
                        </FormButton>
                        <Link
                            class="text-sm font-medium text-teal-700 hover:text-teal-900 dark:text-teal-300 dark:hover:text-teal-100"
                            href="/admin/managed-processes/definitions"
                        >
                            Process definitions
                        </Link>
                    </div>
                </AtlasForm>
            </SurfaceCard>

            <DataTable
                title="Registered search indexes"
                :rows="indexes"
                :columns="indexColumns"
                row-key="key"
                state-key="admin.search.indexes"
                export-key="admin.search.indexes"
                :exports="exports"
                empty-label="No search index descriptors are registered."
            />

            <DataTable
                title="Recent rebuild runs"
                :rows="recentRebuilds"
                :columns="rebuildColumns"
                row-key="publicId"
                :actions="rebuildActions"
                state-key="admin.search.rebuilds"
                export-key="admin.search.rebuilds"
                :exports="exports"
                empty-label="No search rebuild runs recorded."
            />
        </PageStack>
    </AdminLayout>
</template>
