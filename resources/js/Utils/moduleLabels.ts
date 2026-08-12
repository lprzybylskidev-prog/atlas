import type { TranslationKey } from '../Localization/catalog';
import { isMissingTranslation } from '../Localization/translator';

type Translator = (key: TranslationKey, params?: Record<string, string | number>) => string;

export function moduleLabel(key: string, t: Translator): string {
    const normalized = key.trim().replaceAll('-', '_');
    const translationKey = `pages.admin.dashboard.module.${normalized}`;
    const translated = t(translationKey);

    if (!isMissingTranslation(translated, translationKey)) {
        return translated;
    }

    return t('pages.admin.dashboard.module.unknown', { module: key });
}
