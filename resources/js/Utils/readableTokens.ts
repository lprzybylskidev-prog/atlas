import { formatStatus } from './formatters';

export interface ReadableFilterOption {
    value: string;
    label: string;
}

export function readableToken(value: string | null | undefined): string {
    return value === null || value === undefined || value === '' ? '' : formatStatus(value);
}

export function readableFilterOption(option: ReadableFilterOption): string {
    return option.label === option.value ? readableToken(option.value) : option.label;
}
