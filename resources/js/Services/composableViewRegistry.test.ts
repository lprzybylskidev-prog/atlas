import { describe, expect, it } from 'vitest';

import type { ComposableViewElementDefinition } from '../Types/composable-view';
import { resolveComposableHostView } from './composableViewRegistry';
import { foundationDashboardElements } from './foundationDashboardElements';

describe('resolveComposableHostView', () => {
    it('resolves dashboard elements in coded placement order', () => {
        const view = resolveComposableHostView('app.dashboard', foundationDashboardElements);

        expect(view.elements.map((element) => element.definition.key)).toEqual([
            'foundation.dashboard.introduction',
            'foundation.dashboard.active-team',
            'foundation.dashboard.metrics',
            'foundation.dashboard.next-steps',
            'foundation.dashboard.composable-view-contract',
        ]);
        expect(view.missingStructuralElementKeys).toEqual([]);
    });

    it('removes unavailable optional elements without leaving broken layout slots', () => {
        const view = resolveComposableHostView('app.dashboard', foundationDashboardElements, [
            {
                elementKey: 'foundation.dashboard.next-steps',
                reason: 'module-inactive',
            },
        ]);

        expect(view.elements.map((element) => element.definition.key)).not.toContain('foundation.dashboard.next-steps');
        expect(view.missingStructuralElementKeys).toEqual([]);
    });

    it('keeps unavailable structural elements so their independent state can render', () => {
        const view = resolveComposableHostView('app.dashboard', foundationDashboardElements, [
            {
                elementKey: 'foundation.dashboard.metrics',
                reason: 'permission-denied',
            },
        ]);

        const metrics = view.elements.find((element) => element.definition.key === 'foundation.dashboard.metrics');

        expect(metrics?.availability.reason).toBe('permission-denied');
    });

    it('reports missing structural definitions explicitly', () => {
        const withoutIntroduction: ComposableViewElementDefinition[] = foundationDashboardElements.filter(
            (element) => element.key !== 'foundation.dashboard.introduction',
        );

        const view = resolveComposableHostView('app.dashboard', withoutIntroduction);

        expect(view.missingStructuralElementKeys).toEqual(['foundation.dashboard.introduction']);
    });
});
