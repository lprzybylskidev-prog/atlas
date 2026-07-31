<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconUserPlus, IconUsers } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import PageStack from '../../../Components/PageStack.vue';
import { useAccountSensitivityOptions } from '../../../Composables/useAccountSensitivityOptions';
import { useAdminUserAccountActions, type AdminUserActionState } from '../../../Composables/useAdminUserAccountActions';
import { runBulkRecordAction } from '../../../Composables/useBulkRecordActions';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableBulkAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import type { FormSelectOption } from '../../../Components/Form/FormSelect.vue';

interface UserRow extends AdminUserActionState, Record<string, unknown> {
    id: number;
    name: string;
    email: string;
    online: boolean;
    accountSensitivity: string;
    impersonationRequiresSensitiveOverride: boolean;
    emailVerifiedAt: string | null;
    twoFactorConfirmedAt: string | null;
    firstPasswordSetAt: string | null;
    deactivatedAt: string | null;
    failedLoginAttempts: number;
    loginLockCount: number;
    loginLockedUntil: string | null;
    createdAt: string;
    updatedAt: string;
}

const props = defineProps<{
    users: UserRow[];
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const accountSensitivity = useAccountSensitivityOptions();
const userAccountActions = useAdminUserAccountActions();
const tableActions = userAccountActions.tableActions<UserRow>();
const bulkActions = userAccountActions.bulkActions;
const filterKeys = ['status', 'email', 'password', 'mfa', 'lock', 'sensitivity'];
const filterDefaults = {
    status: 'all',
    email: 'all',
    password: 'all',
    mfa: 'all',
    lock: 'all',
    sensitivity: 'all',
};

const filters = ref({ ...filterDefaults, ...filterValues() });

const rows = computed<UserRow[]>(() =>
    props.users.map((user) => ({
        ...user,
        accountSensitivity: accountSensitivity.label(user.accountSensitivity),
    })),
);

const columns = computed<DataTableColumn<UserRow>[]>(() => [
    { key: 'publicId', label: t('pages.admin.users.table.public_id') },
    { key: 'id', label: t('pages.admin.users.table.internal_id'), hidden: true },
    { key: 'name', label: t('pages.admin.users.table.name') },
    { key: 'email', label: t('pages.admin.users.table.email') },
    { key: 'online', label: t('pages.admin.users.table.online'), format: 'boolean' },
    { key: 'isActive', label: t('pages.admin.users.table.active'), format: 'boolean' },
    { key: 'emailVerified', label: t('pages.admin.users.table.email_verified'), format: 'boolean' },
    { key: 'firstPasswordSet', label: t('pages.admin.users.table.first_password_set'), format: 'boolean' },
    { key: 'loginLocked', label: t('pages.admin.users.table.login_locked'), format: 'boolean' },
    { key: 'mfaEnabled', label: t('pages.admin.users.table.mfa_enabled'), format: 'boolean' },
    { key: 'accountSensitivity', label: t('pages.admin.users.table.account_sensitivity') },
    { key: 'emailVerifiedAt', label: t('pages.admin.users.table.email_verified_at'), format: 'datetime', hidden: true },
    { key: 'twoFactorConfirmedAt', label: t('pages.admin.users.table.mfa_confirmed_at'), format: 'datetime', hidden: true },
    { key: 'firstPasswordSetAt', label: t('pages.admin.users.table.first_password_set_at'), format: 'datetime', hidden: true },
    { key: 'deactivatedAt', label: t('pages.admin.users.table.deactivated_at'), format: 'datetime', hidden: true },
    { key: 'failedLoginAttempts', label: t('pages.admin.users.table.failed_login_attempts'), format: 'number', hidden: true },
    { key: 'loginLockCount', label: t('pages.admin.users.table.login_lock_count'), format: 'number', hidden: true },
    { key: 'loginLockedUntil', label: t('pages.admin.users.table.login_locked_until'), format: 'datetime', hidden: true },
    { key: 'createdAt', label: t('pages.admin.users.table.created_at'), format: 'datetime', hidden: true },
    { key: 'updatedAt', label: t('pages.admin.users.table.updated_at'), format: 'datetime', hidden: true },
]);

const statusOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.users.filters.any_status') },
    { value: 'active', label: t('pages.admin.users.status.active') },
    { value: 'inactive', label: t('pages.admin.users.status.inactive') },
]);
const emailOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.users.filters.any_email') },
    { value: 'verified', label: t('pages.admin.users.status.email_verified') },
    { value: 'unverified', label: t('pages.admin.users.status.email_unverified') },
]);
const passwordOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.users.filters.any_password') },
    { value: 'set', label: t('pages.admin.users.status.first_password_set') },
    { value: 'pending', label: t('pages.admin.users.status.first_password_pending') },
]);
const mfaOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.users.filters.any_mfa') },
    { value: 'enabled', label: t('pages.admin.users.status.mfa_enabled') },
    { value: 'disabled', label: t('pages.admin.users.status.mfa_disabled') },
]);
const lockOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.users.filters.any_lock') },
    { value: 'locked', label: t('pages.admin.users.status.login_locked') },
    { value: 'unlocked', label: t('pages.admin.users.status.login_unlocked') },
]);
const sensitivityOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.users.filters.any_sensitivity') },
    ...accountSensitivity.options.value,
]);
const tableFilters = computed(() => filterValues());

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

async function handleBulkAction(payload: { action: DataTableBulkAction; rowIds: string[] }): Promise<void> {
    const endpoint = userAccountActions.endpointFor(payload.action.key);

    if (endpoint === undefined) {
        return;
    }

    await runBulkRecordAction({ method: 'post', href: (rowId) => `/admin/users/${rowId}/${endpoint}` }, payload.rowIds);
}

function filterValues(): Record<string, string> {
    return {
        status: String(props.table.state.filters?.status ?? 'all'),
        email: String(props.table.state.filters?.email ?? 'all'),
        password: String(props.table.state.filters?.password ?? 'all'),
        mfa: String(props.table.state.filters?.mfa ?? 'all'),
        lock: String(props.table.state.filters?.lock ?? 'all'),
        sensitivity: String(props.table.state.filters?.sensitivity ?? 'all'),
    };
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}
</script>

<template>
    <Head :title="t('pages.admin.users.index.head_title')" />
    <AdminLayout :title="t('pages.admin.users.index.title')" :title-icon="IconUsers">
        <PageStack>
            <div class="flex justify-end">
                <ActionLink href="/admin/users/create" :icon="IconUserPlus" tone="primary">
                    {{ t('pages.admin.users.actions.create') }}
                </ActionLink>
            </div>

            <FilterPanel
                :title="t('pages.admin.users.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <FormSelect v-model="filters.status" :label="t('pages.admin.users.filters.status')" :options="statusOptions" />
                    <FormSelect v-model="filters.email" :label="t('pages.admin.users.filters.email')" :options="emailOptions" />
                    <FormSelect v-model="filters.password" :label="t('pages.admin.users.filters.password')" :options="passwordOptions" />
                    <FormSelect v-model="filters.mfa" :label="t('pages.admin.users.filters.mfa')" :options="mfaOptions" />
                    <FormSelect v-model="filters.lock" :label="t('pages.admin.users.filters.lock')" :options="lockOptions" />
                    <FormSelect
                        v-model="filters.sensitivity"
                        :label="t('pages.admin.users.filters.sensitivity')"
                        :options="sensitivityOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.users.table.title')"
                :rows="rows"
                :columns="columns"
                row-key="publicId"
                :actions="tableActions"
                :bulk-actions="bulkActions"
                :bulk-action-handler="handleBulkAction"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.users.empty')"
            />
        </PageStack>
    </AdminLayout>
</template>
