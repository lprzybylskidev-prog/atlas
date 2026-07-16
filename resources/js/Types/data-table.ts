export interface DataTableColumn<TRow extends Record<string, unknown>> {
    key: keyof TRow & string;
    label: string;
    sortable?: boolean;
    hidden?: boolean;
    format?: 'boolean' | 'count' | 'date' | 'datetime' | 'list' | 'money' | 'number' | 'percent' | 'status' | 'time';
}

export interface DataTableAction<TRow extends Record<string, unknown>> {
    key: string;
    label: string;
    method?: 'get' | 'post' | 'patch' | 'delete';
    href: (row: TRow) => string;
    confirm?: string;
    tone?: 'neutral' | 'success' | 'warning' | 'danger';
}

export interface DataTableBulkAction {
    key: string;
    label: string;
    tone?: 'neutral' | 'success' | 'warning' | 'danger';
    execution?: 'sync' | 'queued';
}
