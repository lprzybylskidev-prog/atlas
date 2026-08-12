import type { PaginationState, SortingState, VisibilityState } from '@tanstack/vue-table';

export interface PersistedTableState {
    sorting?: SortingState;
    globalFilter?: string;
    columnVisibility?: VisibilityState;
    pagination?: PaginationState;
}

export function tableStateStorageKey(stateKey?: string): string | null {
    return stateKey ? `atlas.datatable.${stateKey}` : null;
}

export function readTableLocalState(storage: Storage | undefined, key: string | null): PersistedTableState {
    if (storage === undefined || key === null) return {};
    try {
        const raw = storage.getItem(key);
        const parsed: unknown = raw === null ? null : JSON.parse(raw);
        return parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed) ? (parsed as PersistedTableState) : {};
    } catch {
        storage.removeItem(key);
        return {};
    }
}

export function writeTableLocalState(storage: Storage | undefined, key: string | null, state: PersistedTableState): void {
    if (storage !== undefined && key !== null) storage.setItem(key, JSON.stringify(state));
}
