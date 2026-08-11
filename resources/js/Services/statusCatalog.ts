import { IconCircleCheck, IconCircleX, IconClock, IconInfoCircle, IconMinus } from '@tabler/icons-vue';
import type { Component } from 'vue';

import type { TranslationKey } from '../Localization/catalog';
import type { StatusBadgeTone } from '../Utils/statusBadge';

export type StatusSurface = 'admin' | 'manager' | 'user' | 'diagnostic';

export interface StatusDefinition {
    key: TranslationKey;
    tone: StatusBadgeTone;
    icon: Component;
    meaning: 'positive' | 'neutral' | 'in-progress' | 'attention' | 'negative';
    surfaces: StatusSurface[];
}

const allSurfaces: StatusSurface[] = ['admin', 'manager', 'user', 'diagnostic'];

const positive = [
    'active',
    'approved',
    'clean',
    'closed',
    'corrected',
    'enabled',
    'ended',
    'final',
    'handled',
    'healthy',
    'normal',
    'ok',
    'released',
    'resolved',
    'success',
    'succeeded',
    'updated',
    'verified',
    'within_limit',
];
const inProgress = ['pending', 'queued', 'running', 'scanning', 'started', 'under_review', 'waiting', 'working'];
const attention = [
    'break',
    'degraded',
    'forced',
    'maintenance',
    'needs_attention',
    'requires_manager_review',
    'unverified',
    'warning',
    'warn',
];
const negative = ['blocked', 'cancelled', 'danger', 'error', 'exceeded', 'failed', 'failure', 'infected', 'rejected', 'unhealthy'];

const aliases: Record<string, string> = {
    failure: 'failed',
    warn: 'warning',
};

const knownKeys = [
    ...positive,
    ...inProgress,
    ...attention,
    ...negative,
    'disabled',
    'draft',
    'expired',
    'half_open',
    'inactivity',
    'inactive',
    'info',
    'logout',
    'module_unavailable',
    'none',
    'no_session',
    'not_applicable',
    'offline',
    'open',
    'other_work',
    'session_superseded',
    'succeeded_with_warnings',
    'team_switched',
    'team_untracked',
    'unavailable',
    'unsupported',
    'work_session',
];

function normalize(value: string): string {
    const normalized = value.toLowerCase().trim().replaceAll(/\s+/gu, '_').replaceAll('-', '_');

    return aliases[normalized] ?? normalized;
}

function definition(token: string): StatusDefinition {
    if (positive.includes(token)) {
        return { key: `datatable.status.${token}`, tone: 'success', icon: IconCircleCheck, meaning: 'positive', surfaces: allSurfaces };
    }

    if (negative.includes(token)) {
        return { key: `datatable.status.${token}`, tone: 'danger', icon: IconCircleX, meaning: 'negative', surfaces: allSurfaces };
    }

    if (inProgress.includes(token)) {
        return { key: `datatable.status.${token}`, tone: 'warning', icon: IconClock, meaning: 'in-progress', surfaces: allSurfaces };
    }

    if (attention.includes(token)) {
        return { key: `datatable.status.${token}`, tone: 'warning', icon: IconInfoCircle, meaning: 'attention', surfaces: allSurfaces };
    }

    return { key: `datatable.status.${token}`, tone: 'neutral', icon: IconMinus, meaning: 'neutral', surfaces: allSurfaces };
}

export const statusCatalog: Readonly<Record<string, StatusDefinition>> = Object.freeze(
    Object.fromEntries([...new Set(knownKeys.map(normalize))].map((key) => [key, definition(key)])),
);

export function statusDefinition(value: string): StatusDefinition | undefined {
    return statusCatalog[normalize(value)];
}

export function statusTranslationKey(value: string): TranslationKey | undefined {
    return statusDefinition(value)?.key;
}
