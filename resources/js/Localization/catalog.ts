export const supportedLocales = ['pl', 'en'] as const;

export type SupportedLocale = (typeof supportedLocales)[number];

export const defaultLocale: SupportedLocale = 'pl';

export type TranslationKey = string;

export function normalizeLocale(locale: string | undefined): SupportedLocale {
    return supportedLocales.includes(locale as SupportedLocale) ? (locale as SupportedLocale) : defaultLocale;
}
