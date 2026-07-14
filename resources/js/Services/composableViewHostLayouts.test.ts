import { describe, expect, it } from 'vitest';

import type { ComposableHostViewDefinition } from '../Types/composable-view';
import { COMPOSABLE_VIEW_HOST_LAYOUTS, getComposableViewHostLayout } from './composableViewHostLayouts';

describe('composable view host layouts', () => {
    it('defines reusable layouts for required host view types', () => {
        expect(COMPOSABLE_VIEW_HOST_LAYOUTS.map((layout) => layout.key)).toEqual([
            'dashboard-sidebar',
            'overview-grid',
            'manager-workspace',
            'operational-status',
        ]);
    });

    it('resolves the layout configured by the host definition', () => {
        const host: ComposableHostViewDefinition = {
            key: 'app.dashboard',
            type: 'dashboard',
            layout: 'dashboard-sidebar',
            titleKey: 'views.dashboard.title',
            fallbackTitle: 'Dashboard',
            acceptedElements: [],
        };

        expect(getComposableViewHostLayout(host).key).toBe('dashboard-sidebar');
    });

    it('rejects layouts that do not support the host view type', () => {
        const host: ComposableHostViewDefinition = {
            key: 'app.dashboard',
            type: 'dashboard',
            layout: 'operational-status',
            titleKey: 'views.dashboard.title',
            fallbackTitle: 'Dashboard',
            acceptedElements: [],
        };

        expect(() => getComposableViewHostLayout(host)).toThrow(
            'Composable host layout [operational-status] does not support host type [dashboard].',
        );
    });
});
