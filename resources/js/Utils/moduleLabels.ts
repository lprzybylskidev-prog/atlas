import type { TranslationKey } from '../Localization/catalog';

type Translator = (key: TranslationKey, params?: Record<string, string | number>) => string;

export function moduleLabel(key: string, t: Translator): string {
    const normalized = key.trim().replaceAll('-', '_');
    const translationKey = `pages.admin.dashboard.module.${normalized}`;
    const translated = t(translationKey);

    if (translated !== translationKey) {
        return translated;
    }

    return normalized
        .split('_')
        .filter((part) => part !== '')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}
