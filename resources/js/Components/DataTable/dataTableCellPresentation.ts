import type { DataTableColumnFormat } from '../../Types/data-table';

export const dataTableBadgeFormats = [
    'activation-status',
    'boolean',
    'severity',
    'status-badge',
] as const satisfies readonly DataTableColumnFormat[];

const badgeFormats: ReadonlySet<DataTableColumnFormat> = new Set(dataTableBadgeFormats);

export function dataTableFormatRendersBadge(format: DataTableColumnFormat | undefined): boolean {
    return format !== undefined && badgeFormats.has(format);
}

export function dataTableFormatUsesOverflowTooltip(format: DataTableColumnFormat | undefined): boolean {
    return !dataTableFormatRendersBadge(format) && format !== 'status';
}

export function dataTableCellContentClass(format: DataTableColumnFormat | undefined): string {
    return dataTableFormatRendersBadge(format) ? 'inline-flex max-w-full overflow-visible py-0.5 align-middle' : 'block min-w-0 truncate';
}
