import { describe, expect, it } from 'vitest';

const vueFiles = import.meta.glob('../../**/*.vue', {
    eager: true,
    import: 'default',
    query: '?raw',
}) as Record<string, string>;

const nativeControlAllowedFiles = new Set(['./FormInput.vue']);

describe('shared form control guardrails', () => {
    it('keeps native form controls inside shared form primitives', () => {
        for (const [file, contents] of Object.entries(vueFiles)) {
            if (nativeControlAllowedFiles.has(file)) {
                continue;
            }

            expect(contents, file).not.toMatch(/<(input|select|textarea)\b/);
        }
    });
});
