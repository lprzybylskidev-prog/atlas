import { describe, expect, it } from 'vitest';

import { navigationRegistryDefinitions, resolveNavigationRegistry } from './registry';
import type { ShellMode } from '../Types/navigation';

const t = (key: string): string => key;

function availableRoutes(kind: 'admin' | 'application'): string[] {
    const definitions = [
        ...navigationRegistryDefinitions.groups.flatMap((group) => group.items),
        ...Object.values(navigationRegistryDefinitions.subnavigation).flatMap((section) => section.items),
        ...navigationRegistryDefinitions.modeDefinitions,
    ];

    return definitions
        .filter((definition) => definition.availabilityKind === kind)
        .flatMap((definition) => (Array.isArray(definition.availability) ? definition.availability : [definition.availability]));
}

function resolve(mode: ShellMode, currentPath: string, section?: 'audit' | 'managed-processes' | 'privacy-retention' | 'work-time') {
    return resolveNavigationRegistry(
        {
            mode,
            currentPath,
            availableAdminRoutes: availableRoutes('admin'),
            availableApplicationRoutes: availableRoutes('application'),
        },
        section,
        [
            { label: 'Backend label', url: 'http://localhost:8000/admin/work-time/summary' },
            { label: 'Object 01ABC', url: null },
        ],
        t,
    );
}

describe('navigation registry', () => {
    it('resolves one route set for every shell renderer and removes the stale manager report route', () => {
        const manager = resolve('manager', '/manager/work-time/other-work');
        const routes = manager.groups.flatMap((group) => group.items.map((item) => item.href));

        expect(routes).toContain('/manager/work-time/summary');
        expect(routes).toContain('/manager/work-time/other-work');
        expect(routes).not.toContain('/time-tracking/manager-report');
        expect(manager.groups.flatMap((group) => group.items).find((item) => item.href === '/manager/work-time/other-work')?.active).toBe(
            true,
        );
    });

    it('preserves applied filters while navigating between manager work-time sections', () => {
        const manager = resolve('manager', '/manager/work-time/summary?team=01TEAM&range=month');
        const routes = manager.groups.flatMap((group) => group.items.map((item) => item.href));

        expect(routes).toContain('/manager/work-time/other-work?team=01TEAM&range=month');
        expect(routes).toContain('/manager/work-time/breaks?team=01TEAM&range=month');
        expect(routes).toContain('/manager/work-time/corrections?team=01TEAM&range=month');
        expect(routes).toContain('/manager/work-time/work-sessions?team=01TEAM&range=month');
    });

    it('applies backend route availability before exposing navigation or mode links', () => {
        const navigation = resolveNavigationRegistry(
            {
                mode: 'admin',
                currentPath: '/admin',
                availableAdminRoutes: ['admin.system-status', 'admin.users.index'],
                availableApplicationRoutes: ['dashboard'],
            },
            undefined,
            [],
            t,
        );

        expect(navigation.groups.flatMap((group) => group.items.map((item) => item.href))).toEqual(['/', '/admin', '/admin/users']);
        expect(navigation.modeLinks.map((link) => link.key)).toEqual(['app', 'admin']);
    });

    it('resolves desktop and mobile subnavigation, active state, query state, and canonical breadcrumb labels together', () => {
        const navigation = resolve('admin', '/admin/work-time/other-work?team=01TEAM&status=pending', 'work-time');

        expect(navigation.subnavigation).toHaveLength(5);
        expect(navigation.subnavigation.find((item) => item.key === 'work-time.other-work')).toMatchObject({
            active: true,
            href: '/admin/work-time/other-work?team=01TEAM&status=pending',
        });
        expect(navigation.breadcrumbs).toEqual([
            { label: 'navigation.work_time_daily', url: 'http://localhost:8000/admin/work-time/summary' },
            { label: 'Object 01ABC', url: null },
        ]);
    });
});
