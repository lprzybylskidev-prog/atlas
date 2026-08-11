import { describe, expect, it } from 'vitest';

import { findFrontendSourceViolations, type FrontendSourceFile } from './frontendSourceGuard';

const vueFiles = import.meta.glob('../**/*.vue', {
    eager: true,
    import: 'default',
    query: '?raw',
}) as Record<string, string>;

const tsFiles = import.meta.glob('../**/*.ts', {
    eager: true,
    import: 'default',
    query: '?raw',
}) as Record<string, string>;

function repositorySources(): FrontendSourceFile[] {
    return Object.entries({ ...vueFiles, ...tsFiles })
        .filter(([path]) => path !== './frontendSourceGuard.ts' && !path.includes('/Guardrails/') && !path.endsWith('.test.ts'))
        .map(([path, contents]) => ({ path, contents }));
}

describe('permanent frontend source guard', () => {
    it('scans a non-vacuous set of real pages and shared sources', () => {
        const sources = repositorySources();

        expect(sources.filter(({ path }) => path.includes('/Pages/') && path.endsWith('.vue')).length).toBeGreaterThanOrEqual(60);
        expect(sources.length).toBeGreaterThanOrEqual(150);
        expect(findFrontendSourceViolations(sources)).toEqual([]);
    });

    it('rejects mutation fixtures for every guarded unsafe pattern', () => {
        const removedManagersPage = ['Admin', 'Managers'].join('/');
        const removedManagerReport = ['', 'time-tracking', 'manager-report'].join('/');
        const fixtures: FrontendSourceFile[] = [
            {
                path: `../Pages/${removedManagersPage}/Index.vue`,
                contents: `<script setup lang="ts">const removed = '${removedManagersPage}'; const statusLabels: Record<string, string> = {}; window.confirm('x')</script><template><Head title="x" /><AppLayout><input><table></table><ShellSubnavigation /></AppLayout></template>`,
            },
            {
                path: '../Pages/TimeTracking/Unsafe.vue',
                contents: `<script setup lang="ts">const fallbackLabels: Record<string, string> = {}; const columns = [{ key: 'publicId', label: 'ID' }]</script><template><Head title="x" /><AppLayout /></template>`,
            },
            {
                path: '../Pages/MissingContracts.vue',
                contents: `<template><div>${removedManagerReport}</div></template>`,
            },
        ];

        expect(findFrontendSourceViolations(fixtures).map(({ rule }) => rule)).toEqual(
            expect.arrayContaining([
                `legacy-reference:${removedManagersPage}`,
                `legacy-reference:${removedManagerReport}`,
                'native-browser-dialog',
                'local-status-map',
                'local-translation-dictionary',
                'page-owned-native-control-or-table',
                'page-owned-navigation',
                'unsafe-technical-column',
                'missing-browser-title',
                'missing-accepted-layout',
            ]),
        );
    });
});
