import type { AtlasAction, AtlasBulkAction } from './actions';

export const dataTableColumnFormats = [
    'boolean',
    'activation-status',
    'count',
    'date',
    'datetime',
    'file-size',
    'list',
    'money',
    'number',
    'percent',
    'severity',
    'status',
    'status-badge',
    'time',
] as const;

export type DataTableColumnFormat = (typeof dataTableColumnFormats)[number];

export type DataTableColumnAccess = 'allowed' | 'forbidden';
export type DataTableColumnVisibility = 'visible' | 'hidden';

export interface DataTableColumn<TRow extends Record<string, unknown>> {
    key: keyof TRow & string;
    label: string;
    sortable?: boolean;
    hidden?: boolean;
    access?: DataTableColumnAccess;
    visibility?: DataTableColumnVisibility;
    format?: DataTableColumnFormat;
}

export type DataTableAction<TRow extends Record<string, unknown>> = AtlasAction<TRow>;
export type DataTableBulkAction = AtlasBulkAction;

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
    detailedAudit?: boolean;
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
    capabilities?: {
        savedViews?: boolean;
        exports?: boolean;
        selection?: boolean;
    };
    exports?: DataTableExportMeta;
}
