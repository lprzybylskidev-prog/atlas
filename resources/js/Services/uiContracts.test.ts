import { describe, expect, it } from 'vitest';

import { actionAvailable, actionIcon, actionTone } from './actionCatalog';
import { statusDefinition } from './statusCatalog';

describe('shared action and status contracts', () => {
    it('maps operation semantics to one icon and tone vocabulary', () => {
        expect(actionIcon({ key: 'remove-user', label: 'Remove', semantic: 'delete' })).toBe(
            actionIcon({ key: 'delete', label: 'Delete', semantic: 'delete' }),
        );
        expect(actionTone({ key: 'remove-user', label: 'Remove', semantic: 'delete' })).toBe('danger');
        expect(actionTone({ key: 'edit-user', label: 'Edit', semantic: 'edit' })).toBe('neutral');
    });

    it('evaluates action availability through the shared predicate', () => {
        const action = {
            key: 'retry',
            label: 'Retry',
            available: (row: { retryable: boolean }) => row.retryable,
        } as const;

        expect(actionAvailable(action, { retryable: true })).toBe(true);
        expect(actionAvailable(action, { retryable: false })).toBe(false);
    });

    it('resolves statuses from the canonical semantic catalog', () => {
        expect(statusDefinition('active')).toMatchObject({ tone: 'success', key: 'datatable.status.active' });
        expect(statusDefinition('failed')).toMatchObject({ tone: 'danger', key: 'datatable.status.failed' });
        expect(statusDefinition('not-a-status')).toBeUndefined();
    });
});
