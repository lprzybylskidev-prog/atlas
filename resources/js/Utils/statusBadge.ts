export type StatusBadgeTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

import { statusDefinition } from '../Services/statusCatalog';

export function statusBadgeToneForToken(value: string): StatusBadgeTone {
    return statusDefinition(value)?.tone ?? 'neutral';
}
