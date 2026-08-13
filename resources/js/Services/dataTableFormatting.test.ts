import { describe, expect, it } from 'vitest';

import { createDataTableFormatting } from './dataTableFormatting';

describe('DataTable status formatting', () => {
    const t = (key: string): string => ({ 'datatable.status.succeeded': 'Zakończone sukcesem' })[key] ?? key;

    it('uses translated catalog statuses and readable fallbacks without diagnostic markers', () => {
        const { formattedText } = createDataTableFormatting(t as never, 'pl');

        expect(formattedText('succeeded', 'status')).toBe('Zakończone sukcesem');
        expect(formattedText('time_tracking', 'status')).toBe('Time Tracking');
        expect(formattedText('Czas pracy', 'status')).toBe('Czas pracy');
    });
});
