import { computed } from 'vue';

import { useTranslator } from '../Localization/translator';
import type { RecordAction } from '../Components/RecordActions.vue';
import type { DataTableAction, DataTableBulkAction } from '../Types/data-table';

export interface AdminUserActionState {
    publicId: string;
    isActive: boolean;
    emailVerified: boolean;
    firstPasswordSet: boolean;
    loginLocked: boolean;
    mfaEnabled: boolean;
    canImpersonate: boolean;
}

interface AccountActionDefinition {
    key: string;
    labelKey: string;
    endpoint: string;
    method?: 'get' | 'post' | 'patch' | 'delete';
    tone?: 'neutral' | 'info' | 'success' | 'warning' | 'danger';
    disabled?: (user: AdminUserActionState) => boolean;
    disabledReasonKey?: string;
    visible?: (user: AdminUserActionState) => boolean;
    bulk?: boolean;
}

const accountActionDefinitions: AccountActionDefinition[] = [
    {
        key: 'activate',
        labelKey: 'pages.admin.users.actions.activate',
        endpoint: 'activate',
        method: 'post',
        tone: 'success',
        disabled: (user) => user.isActive,
        disabledReasonKey: 'pages.admin.users.action_disabled.already_active',
        bulk: true,
    },
    {
        key: 'deactivate',
        labelKey: 'pages.admin.users.actions.deactivate',
        endpoint: 'deactivate',
        method: 'post',
        tone: 'danger',
        disabled: (user) => !user.isActive,
        disabledReasonKey: 'pages.admin.users.action_disabled.already_inactive',
        bulk: true,
    },
    {
        key: 'verify-email',
        labelKey: 'pages.admin.users.actions.verify_email',
        endpoint: 'verify-email',
        method: 'post',
        tone: 'success',
        disabled: (user) => user.emailVerified,
        disabledReasonKey: 'pages.admin.users.action_disabled.email_already_verified',
        bulk: true,
    },
    {
        key: 'require-email-verification',
        labelKey: 'pages.admin.users.actions.require_email_verification',
        endpoint: 'require-email-verification',
        method: 'post',
        tone: 'warning',
        disabled: (user) => !user.emailVerified,
        disabledReasonKey: 'pages.admin.users.action_disabled.email_not_verified',
        bulk: true,
    },
    {
        key: 'first-password',
        labelKey: 'pages.admin.users.actions.send_first_password',
        endpoint: 'resend-first-password',
        method: 'post',
        tone: 'warning',
        disabled: (user) => user.firstPasswordSet,
        disabledReasonKey: 'pages.admin.users.action_disabled.first_password_already_set',
        bulk: true,
    },
    {
        key: 'unlock',
        labelKey: 'pages.admin.users.actions.unlock',
        endpoint: 'unlock',
        method: 'post',
        tone: 'success',
        disabled: (user) => !user.loginLocked,
        disabledReasonKey: 'pages.admin.users.action_disabled.login_not_locked',
        bulk: true,
    },
    {
        key: 'reset-mfa',
        labelKey: 'pages.admin.users.actions.reset_mfa',
        endpoint: 'reset-mfa',
        method: 'post',
        tone: 'warning',
        disabled: (user) => !user.mfaEnabled,
        disabledReasonKey: 'pages.admin.users.action_disabled.mfa_not_enabled',
        bulk: true,
    },
    {
        key: 'impersonate',
        labelKey: 'pages.admin.users.actions.impersonate',
        endpoint: 'impersonate',
        disabled: (user) => !user.canImpersonate,
        disabledReasonKey: 'pages.admin.users.action_disabled.impersonation_unavailable',
        visible: (user) => user.canImpersonate,
    },
    {
        key: 'invalidate-sessions',
        labelKey: 'pages.admin.users.actions.invalidate_sessions',
        endpoint: 'invalidate-sessions',
        method: 'post',
        tone: 'danger',
        bulk: true,
    },
];

export function useAdminUserAccountActions() {
    const { t } = useTranslator();

    function tableActions<TRow extends AdminUserActionState & Record<string, unknown>>() {
        return computed<DataTableAction<TRow>[]>(() => [
            { key: 'edit', label: t('pages.admin.users.actions.edit'), href: (row) => `/admin/users/${row.publicId}/edit` },
            ...accountActionDefinitions.map((action) => ({
                key: action.key,
                label: t(action.labelKey),
                method: action.method,
                href: (row: TRow) => `/admin/users/${row.publicId}/${action.endpoint}`,
                tone: action.tone,
                visible: action.visible === undefined ? undefined : (row: TRow) => action.visible?.(row) ?? true,
                disabled: action.disabled === undefined ? undefined : (row: TRow) => action.disabled?.(row) ?? false,
                disabledReason: action.disabledReasonKey === undefined ? undefined : () => t(action.disabledReasonKey ?? ''),
            })),
        ]);
    }

    const bulkActions = computed<DataTableBulkAction[]>(() =>
        accountActionDefinitions
            .filter((action) => action.bulk === true)
            .map((action) => ({
                key: action.key,
                label: t(action.labelKey),
                tone: action.tone,
            })),
    );

    function recordActions(user: AdminUserActionState) {
        return computed<RecordAction[]>(() =>
            accountActionDefinitions
                .filter((action) => action.visible?.(user) ?? true)
                .map((action) => ({
                    key: action.key,
                    label: t(action.labelKey),
                    method: action.method,
                    href: `/admin/users/${user.publicId}/${action.endpoint}`,
                    tone: action.tone === 'info' ? 'neutral' : action.tone,
                    disabled: action.disabled?.(user) ?? false,
                    disabledReason: action.disabledReasonKey === undefined ? undefined : t(action.disabledReasonKey),
                })),
        );
    }

    function endpointFor(actionKey: string): string | undefined {
        return accountActionDefinitions.find((action) => action.key === actionKey)?.endpoint;
    }

    return {
        tableActions,
        bulkActions,
        recordActions,
        endpointFor,
    };
}
