<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconShieldSearch } from '@tabler/icons-vue';
import { computed, reactive, watch } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import NoticeBanner from '../../../Components/NoticeBanner.vue';
import PageStack from '../../../Components/PageStack.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableColumn, DataTableMeta } from '../../../Types/data-table';

interface SecurityHistoryRow extends Record<string, unknown> {
    publicId: string;
    userName: string;
    userEmail: string;
    userContext: string;
    occurredAt: string;
    action: string;
    result: string;
    source: string;
    teamPublicId: string;
    impersonationSessionId: string;
    reason: string;
}

interface SecurityHistoryFilters {
    userPublicId: string;
}

interface SecurityHistoryOption {
    value: string;
    label: string;
}

const props = defineProps<{
    events: SecurityHistoryRow[];
    table: DataTableMeta;
    filters: SecurityHistoryFilters;
    userOptions: SecurityHistoryOption[];
}>();

const { t } = useTranslator();
const filters = reactive<SecurityHistoryFilters>({ ...props.filters });
const userOptions = [{ value: '', label: 'All users' }, ...props.userOptions];
const showUserColumn = computed(() => props.filters.userPublicId === '');

const columns = computed<DataTableColumn<SecurityHistoryRow>[]>(() => [
    { key: 'userName', label: 'User', hidden: !showUserColumn.value },
    { key: 'userEmail', label: 'User email', hidden: true },
    { key: 'userContext', label: 'User context', hidden: true },
    { key: 'occurredAt', label: t('pages.security_history.table.occurred_at'), format: 'datetime' },
    { key: 'action', label: t('pages.security_history.table.action') },
    { key: 'result', label: t('pages.security_history.table.result'), format: 'severity' },
    { key: 'source', label: t('pages.security_history.table.source') },
    { key: 'teamPublicId', label: t('pages.security_history.table.team') },
    { key: 'impersonationSessionId', label: t('pages.security_history.table.impersonation_session') },
    { key: 'reason', label: t('pages.security_history.table.reason') },
]);

function applyFilters(): void {
    router.get(
        '/admin/audit/security-history',
        {
            user: filters.userPublicId,
        },
        { preserveScroll: true, preserveState: false, replace: true },
    );
}

function clearFilters(): void {
    filters.userPublicId = '';
    applyFilters();
}

watch(
    () => props.filters,
    (nextFilters) => {
        Object.assign(filters, nextFilters);
    },
    { deep: true },
);
</script>

<template>
    <Head :title="t('pages.security_history.head_title')" />
    <AdminLayout :title="t('pages.security_history.title')" :title-icon="IconShieldSearch">
        <PageStack>
            <NoticeBanner :title="t('pages.security_history.bounded_title')">
                {{ t('pages.security_history.bounded') }}
            </NoticeBanner>
            <FilterPanel @apply="applyFilters" @clear="clearFilters">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <FormSelect v-model="filters.userPublicId" class="mt-1" label="User" aria-label="User" :options="userOptions" />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.security_history.title')"
                :rows="events"
                :columns="columns"
                row-key="publicId"
                :table="table"
                :empty-label="t('pages.security_history.empty')"
            />
        </PageStack>
    </AdminLayout>
</template>
