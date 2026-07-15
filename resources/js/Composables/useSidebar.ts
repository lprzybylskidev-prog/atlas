import { ref } from 'vue';

const storageKey = 'atlas.sidebar.collapsed';

const initialCollapsed = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.localStorage.getItem(storageKey) === 'true';
};

const isSidebarCollapsed = ref(initialCollapsed());
const isSidebarTextVisible = ref(!isSidebarCollapsed.value);

const persistCollapsedState = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(storageKey, String(isSidebarCollapsed.value));
};

export const useSidebar = () => {
    const toggleSidebar = (): void => {
        isSidebarCollapsed.value = !isSidebarCollapsed.value;
        isSidebarTextVisible.value = !isSidebarCollapsed.value;
        persistCollapsedState();
    };

    return {
        isSidebarCollapsed,
        isSidebarTextVisible,
        toggleSidebar,
    };
};
