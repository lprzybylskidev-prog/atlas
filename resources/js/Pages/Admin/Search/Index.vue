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
import NoticeBanner from '../../../Components/NoticeBanner.vue';
import PageStack from '../../../Components/PageStack.vue';
import SeverityBadge from '../../../Components/SeverityBadge.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
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
const { t } = useTranslator();

const moduleOptions = computed(() => [
    { value: '', label: t('pages.admin.search.all_modules') },
    ...Array.from(new Set(props.indexes.map((index) => index.moduleKey)))
        .sort()
        .map((value) => ({ value, label: value })),
]);
const indexOptions = computed(() => [
    { value: '', label: t('pages.admin.search.all_indexes') },
    ...props.indexes.map((index) => ({ value: index.key, label: index.key })),
]);
const summaryItems = computed<{ label: string; value: string; icon: Component; tone: string }[]>(() => [
    {
        label: t('pages.admin.search.metric.registered_indexes'),
        value: String(props.summary.indexes),
        icon: IconDatabaseSearch,
        tone: 'teal',
    },
    {
        label: t('pages.admin.search.metric.sensitive_indexes'),
        value: String(props.summary.sensitive),
        icon: IconShieldCheck,
        tone: props.summary.sensitive > 0 ? 'amber' : 'emerald',
    },
    {
        label: t('pages.admin.search.metric.recent_rebuilds'),
        value: String(props.summary.recentRebuilds),
        icon: IconRotateClockwise,
        tone: 'sky',
    },
    {
        label: t('pages.admin.search.metric.active_rebuilds'),
        value: String(props.summary.activeRebuilds),
        icon: IconAlertTriangle,
        tone: props.summary.activeRebuilds > 0 ? 'amber' : 'emerald',
    },
]);

const indexColumns: DataTableColumn<SearchIndex>[] = [
    { key: 'key', label: t('pages.admin.search.index_key') },
    { key: 'moduleKey', label: t('pages.admin.search.module') },
    { key: 'stableAlias', label: t('pages.admin.search.stable_alias') },
    { key: 'searchableFields', label: t('pages.admin.search.searchable'), format: 'list' },
    { key: 'filterableFields', label: t('pages.admin.search.filterable'), format: 'list', hidden: true },
    { key: 'sortableFields', label: t('pages.admin.search.sortable'), format: 'list', hidden: true },
    { key: 'containsSensitiveData', label: t('pages.admin.search.sensitive'), format: 'boolean' },
    { key: 'supportsDeletion', label: t('pages.admin.search.deletion'), format: 'boolean' },
    { key: 'supportsAnonymization', label: t('pages.admin.search.anonymization'), format: 'boolean' },
];
const rebuildColumns: DataTableColumn<RebuildRun>[] = [
    { key: 'status', label: t('pages.admin.search.status'), format: 'severity' },
    { key: 'currentStage', label: t('pages.admin.search.stage') },
    { key: 'progressLabel', label: t('pages.admin.search.progress') },
    { key: 'progressCurrent', label: t('pages.admin.search.done'), format: 'number' },
    { key: 'progressTotal', label: t('pages.admin.search.total'), format: 'number' },
    { key: 'createdAt', label: t('pages.admin.search.created'), format: 'datetime' },
    { key: 'startedAt', label: t('pages.admin.search.started'), format: 'datetime' },
    { key: 'finishedAt', label: t('pages.admin.search.finished'), format: 'datetime' },
];
const rebuildActions: DataTableAction<RebuildRun>[] = [
    { key: 'open', label: t('pages.admin.search.open_logs'), href: (run) => `/admin/managed-processes/${run.publicId}`, tone: 'info' },
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
    <Head :title="t('pages.admin.search.head_title')" />
    <AdminLayout :title="t('pages.admin.search.title')" :title-icon="IconSearch">
        <PageStack>
            <MetricGrid :items="summaryItems" />

            <SurfaceCard :title="t('pages.admin.search.readiness')" :icon="IconSearch" :subtitle="readiness.description">
                <template #actions>
                    <SeverityBadge
                        :value="readinessSeverity(readiness.status)"
                        :label="
                            readiness.blocking ? t('pages.admin.search.blocking_status', { status: readiness.status }) : readiness.status
                        "
                    />
                </template>
            </SurfaceCard>

            <SurfaceCard
                :title="t('pages.admin.search.start_rebuild')"
                :icon="IconRotateClockwise"
                :subtitle="t('pages.admin.search.start_rebuild_subtitle')"
            >
                <AtlasForm class="space-y-4" :processing="form.processing" @submit="startRebuild">
                    <div class="grid gap-4 md:grid-cols-3">
                        <FormSelect v-model="form.module_key" :label="t('pages.admin.search.module')" :options="moduleOptions" />
                        <FormSelect v-model="form.index_key" :label="t('pages.admin.search.index')" :options="indexOptions" />
                        <FormInput
                            v-model="form.confirmation"
                            :label="t('pages.admin.search.confirmation')"
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
                            {{ t('pages.admin.search.start_rebuild') }}
                        </FormButton>
                        <Link
                            class="text-sm font-medium text-teal-700 hover:text-teal-900 dark:text-teal-300 dark:hover:text-teal-100"
                            href="/admin/managed-processes/definitions"
                        >
                            {{ t('pages.admin.search.process_definitions') }}
                        </Link>
                    </div>
                </AtlasForm>
            </SurfaceCard>

            <DataTable
                :title="t('pages.admin.search.registered_indexes')"
                :rows="indexes"
                :columns="indexColumns"
                row-key="key"
                state-key="admin.search.indexes"
                export-key="admin.search.indexes"
                :exports="exports"
                :empty-label="t('pages.admin.search.empty_indexes')"
            />

            <NoticeBanner :title="t('pages.admin.search.bounded_title')">
                {{ t('pages.admin.search.bounded_rebuilds') }}
            </NoticeBanner>

            <DataTable
                :title="t('pages.admin.search.recent_rebuilds')"
                :rows="recentRebuilds"
                :columns="rebuildColumns"
                row-key="publicId"
                :actions="rebuildActions"
                state-key="admin.search.rebuilds"
                export-key="admin.search.rebuilds"
                :exports="exports"
                :empty-label="t('pages.admin.search.empty_rebuilds')"
            />
        </PageStack>
    </AdminLayout>
</template>
