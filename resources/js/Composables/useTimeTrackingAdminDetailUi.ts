import { IconBriefcase, IconClockHour4, IconDatabase, IconFilePencil, IconPlayerPause } from '@tabler/icons-vue';
import type { Component } from 'vue';

import { formatTimeTrackingDuration, timeTrackingContextLabel, timeTrackingStatusLabel } from './useTimeTrackingReportUi';
import type { ShellSubnavigationItem } from '../Types/navigation';
import { formatStatus, formatTimestamp } from '../Utils/formatters';

export interface SummaryItem {
    key: string;
    value: string;
}

export interface DetailSection {
    title: string;
    rows: Record<string, string>[];
}

export interface DetailField {
    key: string;
    label: string;
    value: string;
    formattedValue: string;
    format: 'boolean' | 'duration' | 'plain' | 'status' | 'timestamp';
}

type Translator = (key: string, replacements?: Record<string, string | number>) => string;

export function recordMap(record: SummaryItem[]): Record<string, string> {
    return Object.fromEntries(record.map((item) => [item.key, item.value]));
}

export function sectionRows(sections: DetailSection[], title: string): Record<string, string>[] {
    return sections.find((section) => section.title === title)?.rows ?? [];
}

export function detailFields(keys: string[], values: Record<string, string>, t: Translator, locale: string): DetailField[] {
    return keys
        .filter((key, index, all) => all.indexOf(key) === index)
        .filter((key) => Object.prototype.hasOwnProperty.call(values, key))
        .map((key) => detailField(key, values[key] ?? '', t, locale));
}

export function detailField(key: string, value: string, t: Translator, locale: string): DetailField {
    const format = fieldFormat(key);

    return {
        key,
        label: detailFieldLabel(key, t),
        value,
        formattedValue: detailValue(key, value, t, locale, format),
        format,
    };
}

export function detailFieldLabel(key: string, t: Translator): string {
    const translationKey = `pages.time_tracking.admin_detail.fields.${key}`;
    const translated = t(translationKey);

    return translated === translationKey ? formatStatus(key) : translated;
}

export function detailValue(key: string, value: string | undefined, t: Translator, locale: string, format = fieldFormat(key)): string {
    if (value === undefined || value === '') {
        return '-';
    }

    if (format === 'timestamp') {
        return formatTimestamp(value, locale);
    }

    if (format === 'duration') {
        return formatTimeTrackingDuration(Number(value), t);
    }

    if (format === 'boolean') {
        return value === 'true' ? t('datatable.boolean.yes') : t('datatable.boolean.no');
    }

    if (key === 'source_type') {
        return translatedToken(`pages.time_tracking.admin_operations.correction_sources.${value}`, value, t);
    }

    if (key === 'request_type') {
        return translatedToken(`pages.time_tracking.admin_operations.correction_types.${value}`, value, t);
    }

    if (key === 'action') {
        const prefix = value.startsWith('time_tracking.') ? 'audit_actions' : 'history_actions';

        return translatedToken(`pages.time_tracking.admin_detail.${prefix}.${value}`, value, t);
    }

    if (key === 'actor_scope') {
        return translatedToken(`pages.time_tracking.admin_detail.actor_scopes.${value}`, value, t);
    }

    if (isContextKey(key)) {
        return timeTrackingContextLabel(value, t);
    }

    if (format === 'status') {
        return timeTrackingStatusLabel(value, t);
    }

    return value;
}

export function fieldFormat(key: string): DetailField['format'] {
    if (key === 'exact_seconds' || key.endsWith('_seconds')) {
        return 'duration';
    }

    if (key.endsWith('_at') || key.endsWith('At')) {
        return 'timestamp';
    }

    if (key.startsWith('requires_') || key.endsWith('_confirmed')) {
        return 'boolean';
    }

    if (['approval_status', 'closure_reason', 'kind', 'request_type', 'result', 'status'].includes(key)) {
        return 'status';
    }

    return 'plain';
}

export function isContextKey(key: string): boolean {
    return ['context', 'context_key', 'module', 'module_key'].includes(key);
}

export function translatedToken(key: string, value: string, t: Translator): string {
    const translated = t(key);

    return translated === key ? formatStatus(value) : translated;
}

export function adminDetailSubnavigation(
    active: 'breaks' | 'corrections' | 'other_work' | 'work_sessions',
    t: Translator,
): ShellSubnavigationItem[] {
    return [
        {
            key: 'daily',
            label: t('navigation.work_time_daily'),
            href: '/admin/work-time/summary',
            icon: IconClockHour4,
            active: false,
        },
        {
            key: 'other_work',
            label: t('navigation.work_time_other_work'),
            href: '/admin/work-time/other-work',
            icon: IconBriefcase,
            active: active === 'other_work',
        },
        {
            key: 'breaks',
            label: t('navigation.work_time_breaks'),
            href: '/admin/work-time/breaks',
            icon: IconPlayerPause,
            active: active === 'breaks',
        },
        {
            key: 'corrections',
            label: t('navigation.work_time_corrections'),
            href: '/admin/work-time/corrections',
            icon: IconFilePencil,
            active: active === 'corrections',
        },
        {
            key: 'work_sessions',
            label: t('navigation.work_time_sessions'),
            href: '/admin/work-time/work-sessions',
            icon: IconDatabase,
            active: active === 'work_sessions',
        },
    ];
}

export function adminDetailIcon(kind: 'break' | 'correction' | 'other_work' | 'work_session'): Component {
    if (kind === 'break') {
        return IconPlayerPause;
    }

    if (kind === 'correction') {
        return IconFilePencil;
    }

    if (kind === 'other_work') {
        return IconBriefcase;
    }

    return IconClockHour4;
}
