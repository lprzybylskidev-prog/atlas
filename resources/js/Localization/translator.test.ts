import { describe, expect, it } from 'vitest';

import { isMissingTranslation, translate } from './translator';

describe('frontend translator diagnostics', () => {
    it('keeps the explicit missing marker recognizable by fallback formatters', () => {
        const key = 'pages.example.missing';
        const marker = translate(key);

        expect(marker).toBe(`[translation:${key}]`);
        expect(isMissingTranslation(marker, key)).toBe(true);
        expect(isMissingTranslation(key, key)).toBe(true);
        expect(isMissingTranslation('Translated copy', key)).toBe(false);
    });

    it('interpolates both Atlas braces and Laravel colon placeholders', () => {
        const catalog = {
            'pages.example.braces': 'Source: {source}',
            'pages.example.colon': 'Source: :source',
        };

        expect(translate('pages.example.braces', catalog, { source: 'Manual' })).toBe('Source: Manual');
        expect(translate('pages.example.colon', catalog, { source: 'Manual' })).toBe('Source: Manual');
        expect(translate('pages.example.colon', catalog, { source: 'Preset — Manager' })).not.toContain(':source');
    });
});
