import { describe, expect, it } from 'vitest';

import { dataTableResponsibilityViolations } from './tableResponsibilityGuard';

const dataTableSource = import.meta.glob('../DataTable.vue', {
    eager: true,
    import: 'default',
    query: '?raw',
})['../DataTable.vue'] as string;

describe('DataTable responsibility boundary', () => {
    it('keeps the real host as composition-only architecture evidence', () => {
        expect(dataTableSource).toContain('useDataTableController');
        expect(dataTableResponsibilityViolations(dataTableSource)).toEqual([]);
    });

    it.each([
        ['query synchronization', 'function currentServerState() {}'],
        ['sorting and pagination', 'function initialSorting() {}'],
        ['column state', 'function normalizeColumnVisibility() {}'],
        ['selection', 'function selectAllFiltered() {}'],
        ['local persistence', 'function persistState() {}'],
        ['saved-view orchestration', 'function applySavedView() {}'],
        ['action execution', 'function runRowAction() {}'],
        ['formatting', 'function cellTooltipText() {}'],
    ])('rejects a mutation that moves %s back into the host', (_responsibility, mutation) => {
        expect(dataTableResponsibilityViolations(`${dataTableSource}\n${mutation}`)).not.toEqual([]);
    });

    it('rejects removal of a required focused unit', () => {
        expect(dataTableResponsibilityViolations(dataTableSource.replaceAll('DataTableStateRow', 'RemovedStateRow'))).toContainEqual(
            expect.objectContaining({ responsibility: 'missing focused unit: DataTableStateRow' }),
        );
    });
});
