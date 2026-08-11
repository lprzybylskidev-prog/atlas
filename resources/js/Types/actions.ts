import type { Component } from 'vue';

import type { TranslationKey } from '../Localization/catalog';

export type ActionTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';
export type ActionMethod = 'get' | 'post' | 'patch' | 'put' | 'delete';
export type ActionPlacement = 'primary' | 'secondary' | 'overflow' | 'row' | 'detail' | 'edit' | 'bulk';
export type ActionNavigationMode = 'inertia' | 'native' | 'callback';
export type ActionSemantic =
    | 'acknowledge'
    | 'activate'
    | 'archive'
    | 'create'
    | 'deactivate'
    | 'delete'
    | 'disable'
    | 'edit'
    | 'open'
    | 'rescan'
    | 'retry'
    | 'revoke'
    | 'save'
    | 'show'
    | 'update';

export interface ActionConfirmation<TRow = never> {
    titleKey: TranslationKey;
    descriptionKey: TranslationKey;
    confirmKey: TranslationKey;
    cancelKey?: TranslationKey;
    subject?: string | ((row: TRow) => string);
    tone?: Extract<ActionTone, 'neutral' | 'warning' | 'danger'>;
    irreversible?: boolean;
    typedConfirmation?: string | ((row: TRow) => string);
    reason?: 'optional' | 'required';
    reasonLabelKey?: TranslationKey;
}

export interface AtlasAction<TRow = never> {
    key: string;
    label: string;
    semantic?: ActionSemantic;
    icon?: Component;
    tone?: ActionTone;
    placement?: ActionPlacement | ActionPlacement[];
    href?: string | ((row: TRow) => string);
    endpoint?: string | ((row: TRow) => string);
    method?: ActionMethod;
    navigation?: ActionNavigationMode;
    nativeNavigation?: boolean;
    permission?: string;
    module?: string;
    available?: boolean | ((row: TRow) => boolean);
    visible?: (row: TRow) => boolean;
    disabled?: boolean | ((row: TRow) => boolean);
    disabledReason?: string | ((row: TRow) => string);
    confirm?: string | ((row: TRow) => string) | ActionConfirmation<TRow>;
    reason?: 'optional' | 'required';
    optimistic?: boolean;
    feedback?: 'flash' | 'toast' | 'none';
    refresh?: 'none' | 'current' | string[];
    onAction?: (row: TRow) => void | Promise<void>;
}

export interface AtlasBulkAction extends AtlasAction<string[]> {
    execution?: 'sync' | 'queued';
}
