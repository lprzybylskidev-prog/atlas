import { beforeEach, describe, expect, it } from 'vitest';

import { consumeFlashMessages, resetConsumedFlashMessagesForTests, type InertiaFlashMessage } from './flashMessages';

function success(id?: string): InertiaFlashMessage {
    return { id, type: 'success', key: 'flash.teams.updated' };
}

describe('Inertia flash message consumption', () => {
    beforeEach(resetConsumedFlashMessagesForTests);

    it('renders one backend flash exactly once across layout remounts and retained partial props', () => {
        const message = success('01K2MUTATIONFEEDBACK000001');

        expect(consumeFlashMessages([message])).toEqual([message]);
        expect(consumeFlashMessages([message])).toEqual([]);
    });

    it('renders repeated actions with the same translation key when they have distinct backend ids', () => {
        expect(consumeFlashMessages([success('01K2MUTATIONFEEDBACK000001')])).toHaveLength(1);
        expect(consumeFlashMessages([success('01K2MUTATIONFEEDBACK000002')])).toHaveLength(1);
    });

    it('keeps legacy messages renderable while all Atlas producers migrate through the typed helper', () => {
        expect(consumeFlashMessages([success()])).toHaveLength(1);
        expect(consumeFlashMessages([success()])).toHaveLength(1);
    });
});
