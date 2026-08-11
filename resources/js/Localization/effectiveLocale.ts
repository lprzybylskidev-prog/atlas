import { defaultLocale, normalizeLocale, type SupportedLocale } from './catalog';

let effectiveLocale: SupportedLocale = defaultLocale;

export function setEffectiveLocale(locale: string | undefined): SupportedLocale {
    effectiveLocale = normalizeLocale(locale);

    return effectiveLocale;
}

export function getEffectiveLocale(): SupportedLocale {
    return effectiveLocale;
}

export function intlLocale(locale: string | undefined = getEffectiveLocale()): 'en-US' | 'pl-PL' {
    return normalizeLocale(locale) === 'en' ? 'en-US' : 'pl-PL';
}
