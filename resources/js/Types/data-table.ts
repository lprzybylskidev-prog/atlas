export interface DataTableColumn<TRow extends Record<string, unknown>> {
    key: keyof TRow & string;
    label: string;
    sortable?: boolean;
    hidden?: boolean;
    format?:
        'boolean' | 'count' | 'date' | 'datetime' | 'file-size' | 'list' | 'money' | 'number' | 'percent' | 'severity' | 'status' | 'time';
}

export interface DataTableAction<TRow extends Record<string, unknown>> {
    key: string;
    label: string;
    method?: 'get' | 'post' | 'patch' | 'delete';
    href?: (row: TRow) => string;
    onAction?: (row: TRow) => void | Promise<void>;
    confirm?: string | ((row: TRow) => string);
    nativeNavigation?: boolean;
    tone?: 'neutral' | 'info' | 'success' | 'warning' | 'danger';
    visible?: (row: TRow) => boolean;
    disabled?: (row: TRow) => boolean;
    disabledReason?: string | ((row: TRow) => string);
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

export type DataTableExportFormat = 'csv' | 'xlsx' | 'pdf' | 'browser_print';

export interface DataTableExportMeta {
    endpoint: string;
    formats: DataTableExportFormat[];
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
    exports?: DataTableExportMeta;
}
