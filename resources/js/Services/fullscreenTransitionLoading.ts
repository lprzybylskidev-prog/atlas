import { reactive } from 'vue';

type LoaderTheme = 'light' | 'dark';

interface LoaderState {
    visible: boolean;
    theme: LoaderTheme;
    startedAt: number;
    sequence: number;
}

export const fullscreenTransitionLoading = reactive<LoaderState>({
    visible: false,
    theme: 'light',
    startedAt: 0,
    sequence: 0,
});

function currentTheme(): LoaderTheme {
    if (typeof document === 'undefined') {
        return 'light';
    }

    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

export function beginFullscreenTransitionLoading(theme: LoaderTheme = currentTheme(), minimumDurationMs = 1000): () => void {
    const sequence = fullscreenTransitionLoading.sequence + 1;

    fullscreenTransitionLoading.visible = true;
    fullscreenTransitionLoading.theme = theme;
    fullscreenTransitionLoading.startedAt = performance.now();
    fullscreenTransitionLoading.sequence = sequence;

    return () => {
        const elapsed = performance.now() - fullscreenTransitionLoading.startedAt;
        const remaining = Math.max(0, minimumDurationMs - elapsed);

        window.setTimeout(() => {
            if (fullscreenTransitionLoading.sequence !== sequence) {
                return;
            }

            fullscreenTransitionLoading.visible = false;
        }, remaining);
    };
}
