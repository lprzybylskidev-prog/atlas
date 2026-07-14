import { computed, ref } from 'vue';

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

const applyTheme = (nextTheme: Theme): void => {
    theme.value = nextTheme;
    document.documentElement.classList.toggle('dark', nextTheme === 'dark');
    document.documentElement.dataset.theme = nextTheme;
    window.localStorage.setItem(storageKey, nextTheme);
};

export const useTheme = () => {
    const isDark = computed(() => theme.value === 'dark');

    const toggleTheme = (): void => {
        applyTheme(isDark.value ? 'light' : 'dark');
    };

    return {
        theme,
        isDark,
        toggleTheme,
    };
};
