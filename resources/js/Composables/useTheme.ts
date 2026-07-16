import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import type { AtlasPageProps } from '../Types/inertia';

type Theme = 'light' | 'dark';

const storageKey = 'atlas.theme';

const initialTheme = (): Theme => {
    if (typeof window === 'undefined') {
        return 'light';
    }

    const storedTheme = window.localStorage.getItem(storageKey);

    if (storedTheme === 'light' || storedTheme === 'dark') {
        return storedTheme;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const theme = ref<Theme>(initialTheme());

const normalizeTheme = (value: unknown): Theme | null => {
    return value === 'light' || value === 'dark' ? value : null;
};

const applyTheme = (nextTheme: Theme, persist = true): void => {
    theme.value = nextTheme;
    document.documentElement.classList.toggle('dark', nextTheme === 'dark');
    document.documentElement.dataset.theme = nextTheme;

    if (persist) {
        window.localStorage.setItem(storageKey, nextTheme);
    }
};

if (typeof window !== 'undefined') {
    document.documentElement.classList.toggle('dark', theme.value === 'dark');
    document.documentElement.dataset.theme = theme.value;
}

export const useTheme = () => {
    const page = usePage<AtlasPageProps>();
    const preferredTheme = normalizeTheme(page.props.preferences?.theme);

    if (preferredTheme !== null && preferredTheme !== theme.value) {
        applyTheme(preferredTheme);
    }

    const isDark = computed(() => theme.value === 'dark');

    const toggleTheme = (): void => {
        const nextTheme = isDark.value ? 'light' : 'dark';

        applyTheme(nextTheme);

        router.post(
            '/theme',
            {
                theme: nextTheme,
            },
            {
                preserveScroll: true,
            },
        );
    };

    return {
        theme,
        isDark,
        toggleTheme,
    };
};
