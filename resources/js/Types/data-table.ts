export interface DataTableColumn<TRow extends Record<string, unknown>> {
    key: keyof TRow & string;
    label: string;
    sortable?: boolean;
    hidden?: boolean;
    format?: 'boolean' | 'count' | 'date' | 'datetime' | 'list' | 'money' | 'number' | 'percent' | 'severity' | 'status' | 'time';
}

export interface DataTableAction<TRow extends Record<string, unknown>> {
    key: string;
    label: string;
    method?: 'get' | 'post' | 'patch' | 'delete';
    href: (row: TRow) => string;
    confirm?: string;
    tone?: 'neutral' | 'info' | 'success' | 'warning' | 'danger';
}

export interface DataTableBulkAction {
    key: string;
    label: string;
    tone?: 'neutral' | 'info' | 'success' | 'warning' | 'danger';
    execution?: 'sync' | 'queued';
}

export interface DataTableState {
    page: number;
    perPage: number;
    sort: string;
    direction: 'asc' | 'desc';
    search: string;
    columns: string[];
    columnOrder: string[];
    filters?: Record<string, string | number | boolean | null>;
    grouping?: string[];
    timeRange?: {
        key: string;
        mode: 'fixed' | 'dynamic';
        from?: string | null;
        to?: string | null;
        preset?: string | null;
    } | null;
    view: string | null;
}

export interface DataTableSavedView {
    publicId: string;
    name: string;
    type: 'private' | 'team' | 'system';
    state: Partial<DataTableState>;
    isDefault: boolean;
}

export interface DataTableMeta {
    key: string;
    state: DataTableState;
    pagination: {
        total: number;
        page: number;
        perPage: number;
        from: number;
        to: number;
    };
    savedViews: DataTableSavedView[];
}
