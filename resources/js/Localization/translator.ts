import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

import { defaultLocale, normalizeLocale, translations, type SupportedLocale, type TranslationKey } from './catalog';
import type { AtlasPageProps } from '../Types/inertia';

export function translate(
    key: TranslationKey,
    locale: string | undefined = defaultLocale,
    params: Record<string, string | number> = {},
): string {
    const normalizedLocale = normalizeLocale(locale);
    let message: string = translations[normalizedLocale][key];

    Object.entries(params).forEach(([name, value]) => {
        message = message.replaceAll(`{${name}}`, String(value));
    });

    return message;
}

export function useTranslator(localeOverride?: string) {
    const page = usePage<AtlasPageProps>();
    const locale = computed<SupportedLocale>(() => normalizeLocale(localeOverride ?? page.props.locale));

    return {
        locale,
        t: (key: TranslationKey, params?: Record<string, string | number>): string => translate(key, locale.value, params),
    };
}
