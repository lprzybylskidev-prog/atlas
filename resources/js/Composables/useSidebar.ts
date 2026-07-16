import { ref } from 'vue';

const collapsedStorageKey = 'atlas.sidebar.collapsed';
const expandedNavigationStorageKey = 'atlas.sidebar.navigation.expanded';

const initialCollapsed = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.localStorage.getItem(collapsedStorageKey) === 'true';
};

const initialExpandedNavigationKeys = (): Set<string> => {
    if (typeof window === 'undefined') {
        return new Set();
    }

    const storedValue = window.localStorage.getItem(expandedNavigationStorageKey);

    if (!storedValue) {
        return new Set();
    }

    try {
        const parsedValue: unknown = JSON.parse(storedValue);

        if (!Array.isArray(parsedValue)) {
            return new Set();
        }

        return new Set(parsedValue.filter((value): value is string => typeof value === 'string'));
    } catch {
        return new Set();
    }
};

const isSidebarCollapsed = ref(initialCollapsed());
const isSidebarTextVisible = ref(!isSidebarCollapsed.value);
const expandedNavigationKeys = ref<Set<string>>(initialExpandedNavigationKeys());

const persistCollapsedState = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(collapsedStorageKey, String(isSidebarCollapsed.value));
};

const persistExpandedNavigationState = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(expandedNavigationStorageKey, JSON.stringify([...expandedNavigationKeys.value]));
};

export const useSidebar = () => {
    const toggleSidebar = (): void => {
        isSidebarCollapsed.value = !isSidebarCollapsed.value;
        isSidebarTextVisible.value = !isSidebarCollapsed.value;
        persistCollapsedState();
    };

    const isNavigationNodeExpanded = (key: string): boolean => {
        return expandedNavigationKeys.value.has(key);
    };

    const setNavigationNodeExpanded = (key: string, expanded: boolean): void => {
        const nextExpandedKeys = new Set(expandedNavigationKeys.value);

        if (expanded) {
            nextExpandedKeys.add(key);
        } else {
            nextExpandedKeys.delete(key);
        }

        expandedNavigationKeys.value = nextExpandedKeys;
        persistExpandedNavigationState();
    };

    return {
        isSidebarCollapsed,
        isSidebarTextVisible,
        isNavigationNodeExpanded,
        setNavigationNodeExpanded,
        toggleSidebar,
    };
};
