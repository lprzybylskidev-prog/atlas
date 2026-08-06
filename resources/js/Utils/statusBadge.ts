export type StatusBadgeTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

export function statusBadgeToneForToken(value: string): StatusBadgeTone {
    const normalized = value.toLowerCase().trim().replaceAll(/\s+/gu, '_').replaceAll('-', '_');

    if (
        [
            'approved',
            'closed',
            'corrected',
            'ended',
            'final',
            'handled',
            'normal',
            'ok',
            'released',
            'resolved',
            'success',
            'succeeded',
            'within_limit',
        ].includes(normalized)
    ) {
        return 'success';
    }

    if (
        ['break', 'forced', 'maintenance', 'pending', 'queued', 'requires_manager_review', 'running', 'under_review', 'waiting'].includes(
            normalized,
        )
    ) {
        return 'warning';
    }

    if (['blocked', 'cancelled', 'danger', 'error', 'exceeded', 'failed', 'failure', 'rejected', 'unhealthy'].includes(normalized)) {
        return 'danger';
    }

    if (
        ['active', 'inactivity', 'offline', 'open', 'info', 'other_work', 'started', 'team_switched', 'working', 'work_session'].includes(
            normalized,
        )
    ) {
        return 'info';
    }

    return 'neutral';
}
