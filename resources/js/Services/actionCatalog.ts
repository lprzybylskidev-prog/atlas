import {
    IconArchive,
    IconCircleCheck,
    IconCircleOff,
    IconCircleX,
    IconDots,
    IconExternalLink,
    IconEye,
    IconKey,
    IconLockOpen,
    IconLogout,
    IconMailCheck,
    IconPencil,
    IconPlayerPlay,
    IconPlus,
    IconRefresh,
    IconSettings,
    IconTrash,
    IconUserCheck,
    IconUserOff,
    IconUserScan,
} from '@tabler/icons-vue';
import type { Component } from 'vue';

import type { ActionSemantic, ActionTone, AtlasAction } from '../Types/actions';

const semanticIcons: Record<ActionSemantic, Component> = {
    acknowledge: IconCircleCheck,
    activate: IconUserCheck,
    archive: IconArchive,
    create: IconPlus,
    deactivate: IconUserOff,
    delete: IconTrash,
    disable: IconCircleOff,
    edit: IconPencil,
    open: IconExternalLink,
    rescan: IconRefresh,
    retry: IconPlayerPlay,
    revoke: IconCircleX,
    save: IconSettings,
    show: IconEye,
    update: IconPencil,
};

const keySemantics: Record<string, ActionSemantic> = {
    activate: 'activate',
    acknowledge: 'acknowledge',
    approve: 'acknowledge',
    archive: 'archive',
    configure: 'update',
    correct: 'edit',
    create: 'create',
    deactivate: 'deactivate',
    delete: 'delete',
    details: 'show',
    disable: 'disable',
    edit: 'edit',
    open: 'open',
    read: 'acknowledge',
    rescan: 'rescan',
    retry: 'retry',
    revoke: 'revoke',
    run: 'retry',
    settings: 'update',
    show: 'show',
    update: 'update',
    view: 'show',
};

const exactIcons: Record<string, Component> = {
    'clear-team': IconCircleOff,
    'first-password': IconKey,
    'force-close': IconCircleOff,
    'invalidate-sessions': IconLogout,
    'mark-read': IconCircleCheck,
    'rebuild-index': IconRefresh,
    'reset-mfa': IconRefresh,
    global: IconSettings,
    impersonate: IconUserScan,
    request_correction: IconPencil,
    set_global: IconSettings,
    set_team: IconSettings,
    team: IconSettings,
    terminate: IconLogout,
    unlock: IconLockOpen,
    verify: IconMailCheck,
};

export function actionSemantic<TRow>(action: AtlasAction<TRow>): ActionSemantic | undefined {
    if (action.semantic !== undefined) {
        return action.semantic;
    }

    const normalized = action.key.toLowerCase().replaceAll('_', '-');

    return keySemantics[normalized] ?? Object.entries(keySemantics).find(([key]) => normalized.includes(key))?.[1];
}

export function actionIcon<TRow>(action: AtlasAction<TRow>): Component {
    if (action.icon !== undefined) {
        return action.icon;
    }

    const normalized = action.key.toLowerCase().replaceAll('_', '-');
    const semantic = actionSemantic(action);

    return exactIcons[normalized] ?? (semantic === undefined ? IconDots : semanticIcons[semantic]);
}

export function actionTone<TRow>(action: AtlasAction<TRow>): ActionTone {
    if (action.tone !== undefined) {
        return action.tone;
    }

    const semantic = actionSemantic(action);

    if (semantic === 'delete' || semantic === 'deactivate' || semantic === 'revoke') {
        return 'danger';
    }

    if (semantic === 'archive' || semantic === 'disable' || semantic === 'rescan' || semantic === 'retry') {
        return 'warning';
    }

    if (semantic === 'activate' || semantic === 'acknowledge') {
        return 'success';
    }

    if (semantic === 'open') {
        return 'info';
    }

    return 'neutral';
}

export function actionToneClass<TRow>(action: AtlasAction<TRow>): string {
    return {
        neutral:
            'border-zinc-300 text-zinc-600 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-900 dark:hover:text-zinc-50',
        info: 'border-sky-200 text-sky-700 hover:border-sky-300 hover:bg-sky-50 hover:text-sky-800 dark:border-sky-900 dark:text-sky-300 dark:hover:bg-sky-950',
        success:
            'border-emerald-200 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 dark:border-emerald-900 dark:text-emerald-300 dark:hover:bg-emerald-950',
        warning:
            'border-amber-200 text-amber-700 hover:border-amber-300 hover:bg-amber-50 hover:text-amber-800 dark:border-amber-900 dark:text-amber-300 dark:hover:bg-amber-950',
        danger: 'border-rose-200 text-rose-700 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-800 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950',
    }[actionTone(action)];
}

export function actionAvailable<TRow>(action: AtlasAction<TRow>, row: TRow): boolean {
    return typeof action.available === 'function' ? action.available(row) : (action.available ?? true);
}

export function actionDisabled<TRow>(action: AtlasAction<TRow>, row: TRow): boolean {
    return typeof action.disabled === 'function' ? action.disabled(row) : (action.disabled ?? false);
}

export function actionDisabledReason<TRow>(action: AtlasAction<TRow>, row: TRow): string | undefined {
    return typeof action.disabledReason === 'function' ? action.disabledReason(row) : action.disabledReason;
}

export function actionHref<TRow>(action: AtlasAction<TRow>, row: TRow): string | undefined {
    const target = action.href ?? action.endpoint;

    return typeof target === 'function' ? target(row) : target;
}
