import { describe, expect, it } from 'vitest';

import * as catalog from './catalog';
import { defaultLocale, supportedLocales } from './catalog';

describe('frontend localization catalog contract', () => {
    it('uses Polish as the default locale', () => {
        expect(defaultLocale).toBe('pl');
    });

    it('keeps frontend locale support explicit without owning translated copy', () => {
        expect(supportedLocales).toEqual(['pl', 'en']);
    });

    it('does not reintroduce an independent frontend translation catalog', () => {
        expect('translations' in catalog).toBe(false);
    });
});
