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
});
