import type { TranslationKey } from '../Localization/catalog';

export type UiStateVariant =
    | 'loading-initial'
    | 'loading-refresh'
    | 'empty'
    | 'no-results'
    | 'error-recoverable'
    | 'error-fatal'
    | 'permission-denied'
    | 'module-unavailable'
    | 'offline'
    | 'stale';

export interface UiStateContract {
    variant: UiStateVariant;
    titleKey: TranslationKey;
    descriptionKey?: TranslationKey;
    retryable?: boolean;
}

export interface ActiveFilter {
    key: string;
    label: string;
    valueLabel: string;
}
