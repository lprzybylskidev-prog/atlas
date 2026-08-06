import { formatStatus } from '../Utils/formatters';

type Translator = (key: string) => string;

export function moduleCategoryLabel(category: string, t: Translator): string {
    const keys: Record<string, string> = {
        application: 'pages.admin.modules.categories.application',
        core: 'pages.admin.modules.categories.core',
        optional: 'pages.admin.modules.categories.optional',
    };

    return keys[category] === undefined ? formatStatus(category) : t(keys[category]);
}

export function moduleScopeLabel(scope: string, t: Translator): string {
    const keys: Record<string, string> = {
        global: 'pages.admin.modules.global',
        team: 'pages.admin.modules.team',
    };

    return keys[scope] === undefined ? formatStatus(scope) : t(keys[scope]);
}

export function moduleSourceLabel(source: string, t: Translator): string {
    const keys: Record<string, string> = {
        global: 'pages.admin.modules.sources.global',
        manual: 'pages.admin.modules.sources.manual',
        scheduled: 'pages.admin.modules.sources.scheduled',
        scheduler: 'pages.admin.modules.sources.scheduler',
        system: 'pages.admin.modules.sources.system',
        team: 'pages.admin.modules.sources.team',
    };

    return keys[source] === undefined ? formatStatus(source) : t(keys[source]);
}

export function moduleScheduleStatusLabel(status: string, t: Translator): string {
    const keys: Record<string, string> = {
        applied: 'pages.admin.modules.schedule_status.applied',
        cancelled: 'pages.admin.modules.schedule_status.cancelled',
        failed: 'pages.admin.modules.schedule_status.failed',
        scheduled: 'pages.admin.modules.schedule_status.scheduled',
    };

    return keys[status] === undefined ? formatStatus(status) : t(keys[status]);
}
