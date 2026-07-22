import { describe, expect, it } from 'vitest';

import { formatEmpty, formatFileSize, formatMoney, formatStatus, formatTimestamp, majorToMinor, minorToMajor } from './formatters';

describe('shared formatters', () => {
    it('uses a stable empty value fallback', () => {
        expect(formatEmpty(null)).toBe('-');
        expect(formatEmpty(undefined)).toBe('-');
        expect(formatEmpty('')).toBe('-');
        expect(formatEmpty('Atlas')).toBe('Atlas');
    });

    it('converts money values through integer minor units', () => {
        expect(majorToMinor(10.25)).toBe(1025);
        expect(minorToMajor(1025)).toBe(10.25);
        expect(formatMoney({ amountMinor: 1025, currency: 'PLN' }, 'en-US')).toContain('10.25');
    });

    it('formats technical statuses for display', () => {
        expect(formatStatus('email_verification_required')).toBe('Email Verification Required');
        expect(formatStatus('')).toBe('-');
    });

    it('formats file sizes consistently', () => {
        expect(formatFileSize(512, 'en-US')).toBe('512 B');
        expect(formatFileSize(1536, 'en-US')).toBe('1.5 KB');
    });

    it('formats timestamps for application locales', () => {
        const timestamp = '2026-07-17T09:03:00+02:00';

        expect(formatTimestamp(timestamp, 'en')).toBe('Jul 17, 2026, 9:03 AM');
        expect(formatTimestamp(timestamp, 'pl')).toBe('17.07.2026, 09:03');
    });
});
