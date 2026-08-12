import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';

import type { DataTableMeta, DataTableSavedView } from '../../Types/data-table';
import { compactTableFilters } from './tableQueryState';
import { findSavedView, rememberSavedView, type SavedViewStatePayload } from './tableSavedViewState';

export function useDataTableSavedViews(input: {
    table: () => DataTableMeta | undefined;
    fallbackSort: () => string;
    pagination: Ref<{ pageIndex: number; pageSize: number }>;
    selectedViewId: Ref<string>;
    selectedViewStorageKey: Ref<string | null>;
    savedViewName: Ref<string>;
    savedViewType: Ref<'private' | 'team'>;
    buildState: () => SavedViewStatePayload;
    applyLocally: (view: DataTableSavedView) => void;
    scheduleServerSync: (resetPage?: boolean) => void;
    copyName: (name: string) => string;
}) {
    function selectedView(): DataTableSavedView | undefined {
        return findSavedView(input.table()?.savedViews, input.selectedViewId.value);
    }

    function applySavedView(viewId: string | number): void {
        input.selectedViewId.value = String(viewId);
        rememberSavedView(
            typeof window === 'undefined' ? undefined : window.sessionStorage,
            input.selectedViewStorageKey.value,
            input.selectedViewId.value,
        );
        const view = selectedView();
        if (view === undefined) {
            input.scheduleServerSync(true);
            return;
        }
        input.applyLocally(view);
        router.get(
            window.location.pathname,
            {
                page: 1,
                per_page: input.pagination.value.pageSize,
                sort: view.state.sort ?? input.table()?.state.sort ?? input.fallbackSort(),
                direction: view.state.direction ?? 'asc',
                search: view.state.search ?? '',
                columns: (view.state.columns ?? []).join(','),
                column_order: (view.state.columnOrder ?? []).join(','),
                view: view.publicId,
                ...compactTableFilters(view.state.filters),
            },
            { preserveScroll: true, preserveState: false, replace: true },
        );
    }

    function saveView(): void {
        const table = input.table();
        if (table === undefined || input.savedViewName.value.trim() === '') return;
        router.post(
            '/table-views',
            { table_key: table.key, name: input.savedViewName.value.trim(), type: input.savedViewType.value, state: input.buildState() },
            { preserveScroll: true, preserveState: false },
        );
    }
    function updateView(): void {
        const view = selectedView();
        if (view === undefined || view.type === 'system') return;
        router.patch(
            `/table-views/${view.publicId}`,
            { name: input.savedViewName.value.trim() || view.name, state: input.buildState() },
            { preserveScroll: true, preserveState: false },
        );
    }
    function deleteView(): void {
        const view = selectedView();
        if (view !== undefined && view.type !== 'system')
            router.delete(`/table-views/${view.publicId}`, { preserveScroll: true, preserveState: false });
    }
    function copyView(): void {
        const view = selectedView();
        if (view === undefined) return;
        router.post(
            `/table-views/${view.publicId}/copy`,
            { name: input.savedViewName.value.trim() || input.copyName(view.name), type: input.savedViewType.value },
            { preserveScroll: true, preserveState: false },
        );
    }
    function makeDefaultView(): void {
        const view = selectedView();
        if (view !== undefined) router.post(`/table-views/${view.publicId}/default`, {}, { preserveScroll: true, preserveState: false });
    }

    return { applySavedView, copyView, deleteView, makeDefaultView, saveView, selectedView, updateView };
}
