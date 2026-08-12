export type TableRowSelection = Record<string, boolean>;

export function selectedTableRowIds(selection: TableRowSelection): string[] {
    return Object.entries(selection)
        .filter(([, selected]) => selected)
        .map(([id]) => id);
}

export function selectEveryTableRow(rowIds: string[]): TableRowSelection {
    return Object.fromEntries(rowIds.map((id) => [id, true]));
}
