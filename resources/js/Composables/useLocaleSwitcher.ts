import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { defaultLocale, normalizeLocale, type SupportedLocale } from '../Localization/catalog';
import { beginFullscreenTransitionLoading } from '../Services/fullscreenTransitionLoading';
import type { AtlasPageProps } from '../Types/inertia';

export function useLocaleSwitcher() {
    const page = usePage<AtlasPageProps>();
    const currentLocale = computed<SupportedLocale>(() => normalizeLocale(page.props.locale));
    const nextLocale = computed<SupportedLocale>(() => (currentLocale.value === 'pl' ? 'en' : 'pl'));

    const switchLocale = (): void => {
        const finishLoading = beginFullscreenTransitionLoading();

        router.post(
            '/locale',
            {
                locale: nextLocale.value,
            },
            {
                preserveScroll: true,
                onFinish: finishLoading,
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
