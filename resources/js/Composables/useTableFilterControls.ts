import { router } from '@inertiajs/vue3';

export type TableFilterValues = Record<string, string | number | boolean | null | undefined>;

function currentQuery(): Record<string, string> {
    if (typeof window === 'undefined') {
        return {};
    }

    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function currentPath(): string {
    return typeof window === 'undefined' ? '' : window.location.pathname;
}

export function applyTableFilters(filterKeys: string[], values: TableFilterValues, defaults: TableFilterValues = {}): void {
    const query = currentQuery();

    delete query.page;

    for (const key of filterKeys) {
        delete query[key];

        const value = values[key];
        const defaultValue = defaults[key];

        if (value === null || value === undefined || String(value).trim() === '' || String(value) === String(defaultValue ?? '')) {
            continue;
        }

        query[key] = String(value);
    }

    router.get(currentPath(), query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

export function clearTableFilters(filterKeys: string[]): void {
    const query = currentQuery();

    delete query.page;

    for (const key of filterKeys) {
        delete query[key];
    }

    router.get(currentPath(), query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}
