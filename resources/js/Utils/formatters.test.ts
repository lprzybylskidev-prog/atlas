import { describe, expect, it } from 'vitest';

import { formatEmpty, formatMoney, formatStatus, majorToMinor, minorToMajor } from './formatters';

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
});
