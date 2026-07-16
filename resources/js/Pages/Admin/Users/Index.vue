<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { IconUserPlus } from '@tabler/icons-vue';

import DataTable from '../../../Components/DataTable.vue';
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

defineProps<{
    users: UserRow[];
    table: DataTableMeta;
}>();

const { t } = useTranslator('en');

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
        <section class="space-y-5">
            <div class="flex justify-end">
                <Link
                    href="/admin/users/create"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-teal-700 px-4 text-sm font-medium text-white transition hover:bg-teal-800 focus-visible:outline focus-visible:outline-amber-500 dark:bg-teal-600 dark:hover:bg-teal-500"
                >
                    <IconUserPlus aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    Create user
                </Link>
            </div>

            <DataTable
                title="Users"
                :rows="users"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :bulk-actions="bulkActions"
                :bulk-action-handler="handleBulkAction"
                :table="table"
                ui-locale="en"
            />
        </section>
    </AdminLayout>
</template>
