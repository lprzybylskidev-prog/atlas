import { describe, expect, it } from 'vitest';

import host from './ComposableViewHost.vue?raw';
import viewElement from './ComposableViewElement.vue?raw';

const files: Record<string, string> = {
    'ComposableViewElement.vue': viewElement,
    'ComposableViewHost.vue': host,
};

describe('composable view UI guardrails', () => {
    it('does not use native title attributes for tooltips', () => {
        for (const [file, contents] of Object.entries(files)) {
            expect(contents, file).not.toMatch(/\stitle=/);
        }
    });

    it('keeps light and dark theme classes in composable view renderers', () => {
        const themedFiles = ['ComposableViewElement.vue', 'ComposableViewHost.vue'];

        for (const file of themedFiles) {
            const contents = files[file];

            expect(contents, file).toContain('dark:');
        }
    });

    it('keeps independent loading, empty, error, unavailable, and permission states visible', () => {
        const contents = files['ComposableViewElement.vue'];

        expect(contents).toContain('Loading...');
        expect(contents).toContain('Permission required.');
        expect(contents).toContain('Element unavailable.');
        expect(contents).toContain('result?.empty');
        expect(contents).toContain('error');
    });
});
