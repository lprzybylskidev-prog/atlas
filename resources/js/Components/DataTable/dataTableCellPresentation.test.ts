import { isVNode } from 'vue';
import { describe, expect, it } from 'vitest';

import SeverityBadge from '../SeverityBadge.vue';
import StatusBadge from '../StatusBadge.vue';
import { createDataTableFormatting } from '../../Services/dataTableFormatting';
import { dataTableColumnFormats, type DataTableColumnFormat } from '../../Types/data-table';
import {
    dataTableBadgeFormats,
    dataTableCellContentClass,
    dataTableFormatRendersBadge,
    dataTableFormatUsesOverflowTooltip,
} from './dataTableCellPresentation';

function representativeValue(format: DataTableColumnFormat): unknown {
    if (format === 'boolean' || format === 'activation-status') return true;
    if (format === 'count' || format === 'file-size' || format === 'number' || format === 'percent') return 1;
    if (format === 'list') return ['One'];
    if (format === 'money') return { amountMinor: 100, currency: 'PLN' };

    return format === 'status-badge' || format === 'severity' || format === 'status' ? 'active' : '2026-08-13T09:00:00Z';
}

describe('DataTable badge presentation contract', () => {
    const formatting = createDataTableFormatting(((key: string) => key) as never, 'pl');

    it('classifies every format that actually renders a shared badge', () => {
        for (const format of dataTableColumnFormats) {
            const rendered = formatting.formatCell(representativeValue(format), format);
            const rendersSharedBadge = isVNode(rendered) && (rendered.type === StatusBadge || rendered.type === SeverityBadge);

            expect(dataTableFormatRendersBadge(format), format).toBe(rendersSharedBadge);
        }
    });

    it('gives every badge format an unclipped wrapper and no overflow tooltip', () => {
        expect(dataTableBadgeFormats).toEqual(['activation-status', 'boolean', 'severity', 'status-badge']);

        for (const format of dataTableBadgeFormats) {
            const classes = dataTableCellContentClass(format);

            expect(classes, format).toContain('overflow-visible');
            expect(classes, format).not.toContain('truncate');
            expect(classes, format).not.toContain('overflow-hidden');
            expect(dataTableFormatUsesOverflowTooltip(format), format).toBe(false);
        }
    });
});
