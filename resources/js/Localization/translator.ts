import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

import { defaultLocale, normalizeLocale, translations, type SupportedLocale, type TranslationKey } from './catalog';
import type { AtlasPageProps } from '../Types/inertia';

export function translate(key: TranslationKey, locale: string | undefined = defaultLocale): string {
    const normalizedLocale = normalizeLocale(locale);

    return translations[normalizedLocale][key];
}

export function useTranslator(localeOverride?: string) {
    const page = usePage<AtlasPageProps>();
    const locale = computed<SupportedLocale>(() => normalizeLocale(localeOverride ?? page.props.locale));

    return {
        locale,
        t: (key: TranslationKey): string => translate(key, locale.value),
    };
}
