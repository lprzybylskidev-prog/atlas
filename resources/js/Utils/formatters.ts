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
