import { ref } from 'vue';

const storageKey = 'atlas.sidebar.collapsed';
const textVisibilityDelayMs = 300;

const initialCollapsed = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.localStorage.getItem(storageKey) === 'true';
};

const isSidebarCollapsed = ref(initialCollapsed());
const isSidebarTextVisible = ref(!isSidebarCollapsed.value);
const isSidebarCompact = ref(isSidebarCollapsed.value);
let textVisibilityTimeout: ReturnType<typeof window.setTimeout> | undefined;
let compactLayoutTimeout: ReturnType<typeof window.setTimeout> | undefined;

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

const clearCompactLayoutTimeout = (): void => {
    if (compactLayoutTimeout === undefined) {
        return;
    }

    window.clearTimeout(compactLayoutTimeout);
    compactLayoutTimeout = undefined;
};

export const useSidebar = () => {
    const toggleSidebar = (): void => {
        clearTextVisibilityTimeout();
        clearCompactLayoutTimeout();

        if (isSidebarCollapsed.value) {
            isSidebarCompact.value = false;
            isSidebarCollapsed.value = false;
            persistCollapsedState();

            textVisibilityTimeout = window.setTimeout(() => {
                isSidebarTextVisible.value = true;
                textVisibilityTimeout = undefined;
            }, textVisibilityDelayMs);

            return;
        }

        isSidebarTextVisible.value = false;
        isSidebarCollapsed.value = true;
        persistCollapsedState();

        compactLayoutTimeout = window.setTimeout(() => {
            isSidebarCompact.value = true;
            compactLayoutTimeout = undefined;
        }, textVisibilityDelayMs);
    };

    return {
        isSidebarCollapsed,
        isSidebarCompact,
        isSidebarTextVisible,
        toggleSidebar,
    };
};
