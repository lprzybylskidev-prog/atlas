import { optionsWithAll, yesNoOptionsWithAll } from '../Utils/filterOptions';
import { formatStatus } from '../Utils/formatters';

export function processStatusLabel(status: string, t: (key: string) => string): string {
    const key = `pages.admin.managed_processes.statuses.${status}`;

    return t(key) === key ? formatStatus(status) : t(key);
}

export function processSourceLabel(source: string, t: (key: string) => string): string {
    const key = `pages.admin.managed_processes.sources.${source}`;

    return t(key) === key ? formatStatus(source) : t(key);
}

export function processSeverityLabel(severity: string, t: (key: string) => string): string {
    const key = `pages.admin.managed_processes.severities.${severity}`;

    return t(key) === key ? formatStatus(severity) : t(key);
}

export function yesNoOptions(t: (key: string) => string) {
    return yesNoOptionsWithAll(t('pages.admin.managed_processes.all'), t('datatable.boolean.yes'), t('datatable.boolean.no'));
}

export function managedProcessOptionsWithAll(values: string[], label: string, valueLabel?: (value: string) => string) {
    return optionsWithAll(values, label, valueLabel);
}

export function jsonText(value: Record<string, unknown> | string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '{}';
    }

    if (typeof value === 'string') {
        try {
            return JSON.stringify(JSON.parse(value), null, 2);
        } catch {
            return value;
        }
    }

    return JSON.stringify(value, null, 2);
}
