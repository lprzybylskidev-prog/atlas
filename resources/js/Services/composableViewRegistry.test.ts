import { describe, expect, it } from 'vitest';
import { defineComponent } from 'vue';

import type { ComposableHostViewDefinition, ComposableViewElementDefinition } from '../Types/composable-view';
import { resolveComposableHostView, resolveComposableHostViewDefinition } from './composableViewRegistry';

const testHost: ComposableHostViewDefinition = {
    key: 'app.dashboard',
    type: 'dashboard',
    layout: 'dashboard-sidebar',
    titleKey: 'views.dashboard.title',
    fallbackTitle: 'Dashboard',
    acceptedElements: [
        {
            elementKey: 'test.dashboard.introduction',
            area: 'main',
            order: 10,
            dimensions: {
                minHeightClass: 'min-h-36',
                spanClass: 'xl:col-span-4',
            },
            structural: true,
        },
        {
            elementKey: 'test.dashboard.metrics',
            area: 'main',
            order: 20,
            dimensions: {
                minHeightClass: 'min-h-32',
                spanClass: 'xl:col-span-4',
            },
            structural: true,
        },
        {
            elementKey: 'test.dashboard.audit',
            area: 'aside',
            order: 10,
            dimensions: {
                minHeightClass: 'min-h-36',
                spanClass: 'xl:col-span-1',
            },
            structural: true,
        },
        {
            elementKey: 'test.dashboard.optional',
            area: 'aside',
            order: 20,
            dimensions: {
                minHeightClass: 'min-h-36',
                spanClass: 'xl:col-span-1',
            },
            structural: false,
        },
    ],
};

const testComponent = defineComponent({
    name: 'ComposableViewTestElement',
    template: '<div />',
});

function testElement(key: string, optional = false): ComposableViewElementDefinition {
    return {
        key,
        hostTypes: ['dashboard'],
        hostKeys: ['app.dashboard'],
        titleKey: `${key}.title`,
        fallbackTitle: key,
        descriptionKey: null,
        fallbackDescription: null,
        requirements: {
            permissions: [],
            modules: [],
            activeTeam: 'none',
        },
        component: testComponent,
        dataProvider: async () => ({
            empty: false,
            data: null,
        }),
        cacheTtlSeconds: null,
        realtime: {
            supported: false,
            channel: null,
        },
        optional,
    };
}

const testElements: readonly ComposableViewElementDefinition[] = [
    testElement('test.dashboard.introduction'),
    testElement('test.dashboard.metrics'),
    testElement('test.dashboard.audit'),
    testElement('test.dashboard.optional', true),
];

describe('resolveComposableHostView', () => {
    it('keeps the current dashboard host empty until real module elements exist', () => {
        const view = resolveComposableHostView('app.dashboard', []);

        expect(view.elements).toEqual([]);
        expect(view.missingStructuralElementKeys).toEqual([]);
    });

    it('resolves dashboard elements in coded placement order', () => {
        const view = resolveComposableHostViewDefinition(testHost, testElements);

        expect(view.elements.map((element) => element.definition.key)).toEqual([
            'test.dashboard.introduction',
            'test.dashboard.audit',
            'test.dashboard.metrics',
            'test.dashboard.optional',
        ]);
        expect(view.missingStructuralElementKeys).toEqual([]);
    });

    it('removes unavailable optional elements without leaving broken layout slots', () => {
        const view = resolveComposableHostViewDefinition(testHost, testElements, [
            {
                elementKey: 'test.dashboard.optional',
                reason: 'module-inactive',
            },
        ]);

        expect(view.elements.map((element) => element.definition.key)).not.toContain('test.dashboard.optional');
        expect(view.missingStructuralElementKeys).toEqual([]);
    });

    it('keeps unavailable structural elements so their independent state can render', () => {
        const view = resolveComposableHostViewDefinition(testHost, testElements, [
            {
                elementKey: 'test.dashboard.metrics',
                reason: 'permission-denied',
            },
        ]);

        const metrics = view.elements.find((element) => element.definition.key === 'test.dashboard.metrics');

        expect(metrics?.availability.reason).toBe('permission-denied');
    });

    it('reports missing structural definitions explicitly', () => {
        const withoutIntroduction: ComposableViewElementDefinition[] = testElements.filter(
            (element) => element.key !== 'test.dashboard.introduction',
        );

        const view = resolveComposableHostViewDefinition(testHost, withoutIntroduction);

        expect(view.missingStructuralElementKeys).toEqual(['test.dashboard.introduction']);
    });
});
