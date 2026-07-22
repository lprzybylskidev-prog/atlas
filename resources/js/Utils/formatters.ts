export type EmptyValue = null | undefined | '';

export interface MoneyValue {
    amountMinor: number;
    currency: string;
}

export function isEmptyValue(value: unknown): value is EmptyValue {
    return value === null || value === undefined || value === '';
}

export function formatEmpty(value: unknown, fallback = '-'): string {
    return isEmptyValue(value) ? fallback : String(value);
}

export function formatNumber(value: number | EmptyValue, locale = 'pl-PL', options: Intl.NumberFormatOptions = {}): string {
    if (isEmptyValue(value)) {
        return formatEmpty(value);
    }

    return new Intl.NumberFormat(locale, options).format(value);
}

export function formatPercent(value: number | EmptyValue, locale = 'pl-PL'): string {
    if (isEmptyValue(value)) {
        return formatEmpty(value);
    }

    return new Intl.NumberFormat(locale, { maximumFractionDigits: 2, style: 'percent' }).format(value);
}

export function formatFileSize(value: number | EmptyValue, locale = 'pl-PL'): string {
    if (isEmptyValue(value)) {
        return formatEmpty(value);
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = value;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    const maximumFractionDigits = unitIndex === 0 ? 0 : 1;

    return `${new Intl.NumberFormat(locale, { maximumFractionDigits }).format(size)} ${units[unitIndex]}`;
}

export function minorToMajor(amountMinor: number): number {
    return amountMinor / 100;
}

export function majorToMinor(amountMajor: number): number {
    return Math.round(amountMajor * 100);
}

export function formatMoney(value: MoneyValue | EmptyValue, locale = 'pl-PL'): string {
    if (isEmptyValue(value)) {
        return formatEmpty(value);
    }

    return new Intl.NumberFormat(locale, {
        currency: value.currency,
        style: 'currency',
    }).format(minorToMajor(value.amountMinor));
}

export function formatDate(value: string | Date | EmptyValue, locale = 'pl-PL'): string {
    if (isEmptyValue(value)) {
        return formatEmpty(value);
    }

    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(value));
}

export function formatTime(value: string | Date | EmptyValue, locale = 'pl-PL'): string {
    if (isEmptyValue(value)) {
        return formatEmpty(value);
    }

    return new Intl.DateTimeFormat(locale, { timeStyle: 'short' }).format(new Date(value));
}

export function formatDateTime(value: string | Date | EmptyValue, locale = 'pl-PL'): string {
    if (isEmptyValue(value)) {
        return formatEmpty(value);
    }

    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

export function formatTimestamp(value: string | Date | EmptyValue, locale = 'pl'): string {
    if (isEmptyValue(value)) {
        return formatEmpty(value);
    }

    if (locale.startsWith('pl')) {
        return new Intl.DateTimeFormat('pl-PL', {
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).format(new Date(value));
    }

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

export function formatStatus(value: string | EmptyValue): string {
    if (isEmptyValue(value)) {
        return formatEmpty(value);
    }

    return value
        .split(/[-_\s]+/u)
        .filter(Boolean)
        .map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`)
        .join(' ');
}
