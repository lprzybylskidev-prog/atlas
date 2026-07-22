import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

import { normalizeLocale, type SupportedLocale, type TranslationKey } from './catalog';
import type { AtlasPageProps } from '../Types/inertia';

export function translate(key: TranslationKey, catalog: Record<string, string> = {}, params: Record<string, string | number> = {}): string {
    let message: string = catalog[key] ?? key;

    Object.entries(params).forEach(([name, value]) => {
        message = message.replaceAll(`{${name}}`, String(value));
    });

    return message;
}

export function useTranslator(localeOverride?: string) {
    const page = usePage<AtlasPageProps>();
    const locale = computed<SupportedLocale>(() => normalizeLocale(localeOverride ?? page.props.locale));
    const catalog = computed<Record<string, string>>(() => page.props.translations ?? {});

    return {
        locale,
        t: (key: TranslationKey, params?: Record<string, string | number>): string => translate(key, catalog.value, params),
    };
}
