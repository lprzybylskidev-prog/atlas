import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { defaultLocale, normalizeLocale, type SupportedLocale } from '../Localization/catalog';
import type { AtlasPageProps } from '../Types/inertia';

export function useLocaleSwitcher() {
    const page = usePage<AtlasPageProps>();
    const currentLocale = computed<SupportedLocale>(() => normalizeLocale(page.props.locale));
    const nextLocale = computed<SupportedLocale>(() => (currentLocale.value === 'pl' ? 'en' : 'pl'));

    const switchLocale = (): void => {
        router.post(
            '/locale',
            {
                locale: nextLocale.value,
            },
            {
                preserveScroll: true,
            },
        );
    };

    return {
        currentLocale,
        nextLocale,
        switchLocale,
        defaultLocale,
    };
}
