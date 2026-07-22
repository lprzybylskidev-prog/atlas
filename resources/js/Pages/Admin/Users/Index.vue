<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconUserPlus } from '@tabler/icons-vue';
import { computed, reactive } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import PageStack from '../../../Components/PageStack.vue';
import { runBulkRecordAction } from '../../../Composables/useBulkRecordActions';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableBulkAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';

interface UserRow extends Record<string, unknown> {
    id: number;
    publicId: string;
    name: string;
    email: string;
    isActive: boolean;
    emailVerified: boolean;
    firstPasswordSet: boolean;
    loginLocked: boolean;
    mfaEnabled: boolean;
    online: boolean;
    accountSensitivity: string;
    canImpersonate: boolean;
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

const { t } = useTranslator();

const filters = reactive({
    status: 'all',
    email: 'all',
    password: 'all',
    mfa: 'all',
    lock: 'all',
    sensitivity: 'all',
});

const statusOptions = [
    { value: 'all', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Deactivated' },
];

const emailOptions = [
    { value: 'all', label: 'Any email state' },
    { value: 'verified', label: 'Verified' },
    { value: 'unverified', label: 'Unverified' },
];

const passwordOptions = [
    { value: 'all', label: 'Any password state' },
    { value: 'set', label: 'First password set' },
    { value: 'pending', label: 'First password pending' },
];

const mfaOptions = [
    { value: 'all', label: 'Any MFA state' },
    { value: 'enabled', label: 'MFA confirmed' },
    { value: 'disabled', label: 'MFA not confirmed' },
];

const lockOptions = [
    { value: 'all', label: 'Any lock state' },
    { value: 'locked', label: 'Locked' },
    { value: 'unlocked', label: 'Unlocked' },
];

const sensitivityOptions = computed(() => [
    { value: 'all', label: 'All sensitivities' },
    ...Array.from(new Set(props.users.map((user) => user.accountSensitivity)))
        .filter((value) => value !== '')
        .sort((left, right) => left.localeCompare(right))
        .map((value) => ({ value, label: value })),
]);

const filteredUsers = computed(() =>
    props.users.filter((user) => {
        if (filters.status === 'active' && !user.isActive) {
            return false;
        }

        if (filters.status === 'inactive' && user.isActive) {
            return false;
        }

        if (filters.email === 'verified' && !user.emailVerified) {
            return false;
        }

        if (filters.email === 'unverified' && user.emailVerified) {
            return false;
        }

        if (filters.password === 'set' && !user.firstPasswordSet) {
            return false;
        }

        if (filters.password === 'pending' && user.firstPasswordSet) {
            return false;
        }

        if (filters.mfa === 'enabled' && !user.mfaEnabled) {
            return false;
        }

        if (filters.mfa === 'disabled' && user.mfaEnabled) {
            return false;
        }

        if (filters.lock === 'locked' && !user.loginLocked) {
            return false;
        }

        if (filters.lock === 'unlocked' && user.loginLocked) {
            return false;
        }

        return filters.sensitivity === 'all' || user.accountSensitivity === filters.sensitivity;
    }),
);

function resetFilters(): void {
    filters.status = 'all';
    filters.email = 'all';
    filters.password = 'all';
    filters.mfa = 'all';
    filters.lock = 'all';
    filters.sensitivity = 'all';
}

const columns: DataTableColumn<UserRow>[] = [
    { key: 'publicId', label: 'Public ID' },
    { key: 'id', label: 'ID', hidden: true },
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'isActive', label: 'Active', format: 'boolean' },
    { key: 'emailVerified', label: 'Email verified', format: 'boolean' },
    { key: 'firstPasswordSet', label: 'Password set', format: 'boolean' },
    { key: 'loginLocked', label: 'Locked', format: 'boolean' },
    { key: 'mfaEnabled', label: 'MFA', format: 'boolean' },
    { key: 'online', label: 'Online', format: 'boolean' },
    { key: 'accountSensitivity', label: 'Sensitivity' },
    { key: 'emailVerifiedAt', label: 'Email verified at', format: 'datetime', hidden: true },
    { key: 'twoFactorConfirmedAt', label: 'MFA confirmed at', format: 'datetime', hidden: true },
    { key: 'firstPasswordSetAt', label: 'First password set at', format: 'datetime', hidden: true },
    { key: 'deactivatedAt', label: 'Deactivated at', format: 'datetime', hidden: true },
    { key: 'failedLoginAttempts', label: 'Failed login attempts', hidden: true },
    { key: 'loginLockCount', label: 'Login lock count', hidden: true },
    { key: 'loginLockedUntil', label: 'Login locked until', format: 'datetime', hidden: true },
    { key: 'createdAt', label: 'Created at', format: 'datetime', hidden: true },
    { key: 'updatedAt', label: 'Updated at', format: 'datetime', hidden: true },
];

const actions: DataTableAction<UserRow>[] = [
    { key: 'edit', label: 'Edit', href: (row) => `/admin/users/${row.publicId}/edit` },
    { key: 'activate', label: 'Activate', method: 'post', href: (row) => `/admin/users/${row.publicId}/activate` },
    { key: 'deactivate', label: 'Deactivate', method: 'post', href: (row) => `/admin/users/${row.publicId}/deactivate` },
    { key: 'verify', label: 'Verify email', method: 'post', href: (row) => `/admin/users/${row.publicId}/verify-email` },
    {
        key: 'require-email-verification',
        label: 'Require re-verification',
        method: 'post',
        href: (row) => `/admin/users/${row.publicId}/require-email-verification`,
    },
    { key: 'first-password', label: 'Send link', method: 'post', href: (row) => `/admin/users/${row.publicId}/resend-first-password` },
    { key: 'unlock', label: 'Unlock', method: 'post', href: (row) => `/admin/users/${row.publicId}/unlock` },
    { key: 'reset-mfa', label: 'Reset MFA', method: 'post', href: (row) => `/admin/users/${row.publicId}/reset-mfa` },
    {
        key: 'impersonate',
        label: 'Impersonate',
        href: (row) => `/admin/users/${row.publicId}/impersonate`,
        visible: (row) => row.canImpersonate,
    },
    {
        key: 'invalidate-sessions',
        label: 'Invalidate sessions',
        method: 'post',
        href: (row) => `/admin/users/${row.publicId}/invalidate-sessions`,
    },
];

const bulkActions: DataTableBulkAction[] = [
    { key: 'activate', label: 'Activate', tone: 'success' },
    { key: 'deactivate', label: 'Deactivate', tone: 'danger' },
    { key: 'verify', label: 'Verify email', tone: 'success' },
    { key: 'require-email-verification', label: 'Require re-verification', tone: 'warning' },
    { key: 'first-password', label: 'Send link', tone: 'warning' },
    { key: 'unlock', label: 'Unlock', tone: 'success' },
    { key: 'reset-mfa', label: 'Reset MFA', tone: 'warning' },
    { key: 'invalidate-sessions', label: 'Invalidate sessions', tone: 'danger' },
];

async function handleBulkAction(payload: { action: DataTableBulkAction; rowIds: string[] }): Promise<void> {
    const endpoints: Record<string, string> = {
        activate: 'activate',
        deactivate: 'deactivate',
        verify: 'verify-email',
        'require-email-verification': 'require-email-verification',
        'first-password': 'resend-first-password',
        unlock: 'unlock',
        'reset-mfa': 'reset-mfa',
        'invalidate-sessions': 'invalidate-sessions',
    };
    const endpoint = endpoints[payload.action.key];

    if (endpoint === undefined) {
        return;
    }

    await runBulkRecordAction(
        {
            method: 'post',
            href: (rowId) => `/admin/users/${rowId}/${endpoint}`,
        },
        payload.rowIds,
    );
}
</script>

<template>
    <Head :title="t('pages.admin.users.index.head_title')" />
    <AdminLayout :title="t('pages.admin.users.index.title')" :title-icon="IconUserPlus">
        <PageStack>
            <div class="flex justify-end">
                <ActionLink href="/admin/users/create" :icon="IconUserPlus" tone="primary"> Create user </ActionLink>
            </div>

            <FilterPanel
                title="User filters"
                :summary="`Showing ${filteredUsers.length} of ${users.length} loaded users.`"
                @apply="() => {}"
                @clear="resetFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <FormSelect v-model="filters.status" label="Status" :options="statusOptions" />
                    <FormSelect v-model="filters.email" label="Email" :options="emailOptions" />
                    <FormSelect v-model="filters.password" label="Password" :options="passwordOptions" />
                    <FormSelect v-model="filters.mfa" label="MFA" :options="mfaOptions" />
                    <FormSelect v-model="filters.lock" label="Lock" :options="lockOptions" />
                    <FormSelect v-model="filters.sensitivity" label="Sensitivity" :options="sensitivityOptions" />
                </div>
            </FilterPanel>

            <DataTable
                title="Users"
                :rows="filteredUsers"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :bulk-actions="bulkActions"
                :bulk-action-handler="handleBulkAction"
                :table="table"
                :filters="filters"
            />
        </PageStack>
    </AdminLayout>
</template>
