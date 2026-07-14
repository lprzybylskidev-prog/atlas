import { describe, expect, it } from 'vitest';

import { defaultLocale, supportedLocales, translations } from './catalog';

describe('frontend translation catalog', () => {
    it('uses Polish as the default locale', () => {
        expect(defaultLocale).toBe('pl');
    });

    it('keeps all supported locale catalogs in parity', () => {
        const defaultKeys = Object.keys(translations[defaultLocale]).sort();

        for (const locale of supportedLocales) {
            expect(Object.keys(translations[locale]).sort(), locale).toEqual(defaultKeys);
        }
    });
});
