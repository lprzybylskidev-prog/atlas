import { ref } from 'vue';

const storageKey = 'atlas.sidebar.collapsed';

const initialCollapsed = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.localStorage.getItem(storageKey) === 'true';
};

const isSidebarCollapsed = ref(initialCollapsed());

export const useSidebar = () => {
    const toggleSidebar = (): void => {
        isSidebarCollapsed.value = !isSidebarCollapsed.value;
        window.localStorage.setItem(storageKey, String(isSidebarCollapsed.value));
    };

    return {
        isSidebarCollapsed,
        toggleSidebar,
    };
};
