import { router } from '@inertiajs/vue3';
import type { Component, ComputedRef } from 'vue';
import { nextTick } from 'vue';

import { useModal } from '../../Composables/useModal';
import { useToast } from '../../Composables/useToast';
import type { TranslationKey } from '../../Localization/catalog';
import {
    actionAvailable,
    actionDisabled,
    actionDisabledReason,
    actionHref,
    actionIcon,
    actionSemantic,
    actionTone,
    actionToneClass,
} from '../../Services/actionCatalog';
import type { DataTableAction, DataTableBulkAction } from '../../Types/data-table';

export function useDataTableActions<TRow extends Record<string, unknown>>(input: {
    actions: () => DataTableAction<TRow>[];
    selectedRowIds: ComputedRef<string[]>;
    bulkActionHandler?: (payload: { action: DataTableBulkAction; rowIds: string[] }) => void | Promise<void>;
    emitBulkAction: (payload: { action: DataTableBulkAction; rowIds: string[] }) => void;
}) {
    const { busy, confirm } = useModal();
    const toast = useToast();

    function visibleActions(row: TRow): DataTableAction<TRow>[] {
        return input.actions().filter((action) => actionAvailable(action, row) && (action.visible?.(row) ?? true));
    }

    function actionTooltip(action: DataTableAction<TRow>, row: TRow): string {
        const reason = actionDisabledReason(action, row);
        return !actionDisabled(action, row) || reason === undefined || reason.trim() === '' ? action.label : `${action.label}: ${reason}`;
    }

    async function runRowAction(action: DataTableAction<TRow>, row: TRow): Promise<void> {
        if (actionDisabled(action, row)) return;
        const confirmation = action.confirm;
        const subject =
            typeof confirmation === 'function'
                ? confirmation(row)
                : typeof confirmation === 'string'
                  ? confirmation
                  : typeof confirmation?.subject === 'function'
                    ? confirmation.subject(row)
                    : confirmation?.subject;
        const semantic = actionSemantic(action);
        const semanticConfirmationKey =
            semantic !== undefined && ['acknowledge', 'archive', 'deactivate', 'delete', 'rescan', 'retry', 'revoke'].includes(semantic)
                ? `modal.action.${semantic}`
                : null;
        if (
            confirmation !== undefined &&
            !(await confirm({
                titleKey:
                    typeof confirmation === 'object'
                        ? confirmation.titleKey
                        : semanticConfirmationKey === null
                          ? 'datatable.action.confirm.title'
                          : `${semanticConfirmationKey}.title`,
                descriptionKey:
                    typeof confirmation === 'object'
                        ? confirmation.descriptionKey
                        : semanticConfirmationKey === null
                          ? 'datatable.action.confirm.description'
                          : `${semanticConfirmationKey}.description`,
                confirmKey:
                    typeof confirmation === 'object'
                        ? confirmation.confirmKey
                        : semanticConfirmationKey === null
                          ? 'datatable.action.confirm.confirm'
                          : `${semanticConfirmationKey}.confirm`,
                cancelKey: typeof confirmation === 'object' ? confirmation.cancelKey : 'datatable.action.confirm.cancel',
                tone:
                    typeof confirmation === 'object'
                        ? (confirmation.tone ?? (actionTone(action) === 'danger' ? 'danger' : 'warning'))
                        : actionTone(action) === 'danger'
                          ? 'danger'
                          : 'warning',
                subject,
                irreversible: typeof confirmation === 'object' ? confirmation.irreversible : false,
                typedConfirmation:
                    typeof confirmation === 'object'
                        ? typeof confirmation.typedConfirmation === 'function'
                            ? confirmation.typedConfirmation(row)
                            : confirmation.typedConfirmation
                        : undefined,
            }))
        )
            return;
        if (action.onAction !== undefined) {
            await action.onAction(row);
            return;
        }
        const href = actionHref(action, row);
        if (href === undefined) return;
        if (action.nativeNavigation === true) {
            window.location.assign(href);
            return;
        }
        router.visit(href, { method: action.method ?? 'get', preserveScroll: true });
    }

    async function withBusyModal(
        titleKey: TranslationKey,
        descriptionKey: TranslationKey,
        operation: () => void | Promise<void>,
    ): Promise<void> {
        const close = busy({ titleKey, descriptionKey });
        try {
            await nextTick();
            await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
            await operation();
        } finally {
            close();
        }
    }

    function runBulkAction(action: DataTableBulkAction): void {
        if (input.selectedRowIds.value.length === 0) {
            toast.warning('datatable.bulk.no_selection');
            return;
        }
        void confirmBulkAction(action);
    }

    async function confirmBulkAction(action: DataTableBulkAction): Promise<void> {
        if (
            input.selectedRowIds.value.length > 5000 &&
            !(await confirm({
                titleKey: 'datatable.bulk.large.title',
                descriptionKey: 'datatable.bulk.large.description',
                confirmKey: 'datatable.bulk.large.confirm',
                cancelKey: 'datatable.bulk.large.cancel',
                tone: 'warning',
            }))
        )
            return;
        const payload = { action, rowIds: input.selectedRowIds.value };
        const execute = async () => {
            await input.bulkActionHandler?.(payload);
            input.emitBulkAction(payload);
        };
        if (action.execution === 'queued') await execute();
        else await withBusyModal('datatable.bulk.processing.title', 'datatable.bulk.processing.description', execute);
    }

    return {
        actionClass: actionToneClass,
        actionDisabled,
        actionIcon: (action: DataTableAction<TRow>): Component => actionIcon(action),
        actionTooltip,
        catalogActionIcon: actionIcon,
        runBulkAction,
        runRowAction,
        visibleActions,
    };
}
