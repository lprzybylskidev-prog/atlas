import { ref } from 'vue';

const storageKey = 'atlas.sidebar.collapsed';
const textVisibilityDelayMs = 300;
const textHideDelayMs = 80;

const initialCollapsed = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.localStorage.getItem(storageKey) === 'true';
};

const isSidebarCollapsed = ref(initialCollapsed());
const isSidebarTextVisible = ref(!isSidebarCollapsed.value);
let textVisibilityTimeout: ReturnType<typeof window.setTimeout> | undefined;

const persistCollapsedState = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(storageKey, String(isSidebarCollapsed.value));
};

const clearTextVisibilityTimeout = (): void => {
    if (textVisibilityTimeout === undefined) {
        return;
    }

    window.clearTimeout(textVisibilityTimeout);
    textVisibilityTimeout = undefined;
};

export const useSidebar = () => {
    const toggleSidebar = (): void => {
        clearTextVisibilityTimeout();

        if (isSidebarCollapsed.value) {
            isSidebarCollapsed.value = false;
            persistCollapsedState();

            textVisibilityTimeout = window.setTimeout(() => {
                isSidebarTextVisible.value = true;
                textVisibilityTimeout = undefined;
            }, textVisibilityDelayMs);

            return;
        }

        isSidebarCollapsed.value = true;
        persistCollapsedState();

        textVisibilityTimeout = window.setTimeout(() => {
            isSidebarTextVisible.value = false;
            textVisibilityTimeout = undefined;
        }, textHideDelayMs);
    };

    return {
        isSidebarCollapsed,
        isSidebarTextVisible,
        toggleSidebar,
    };
};
