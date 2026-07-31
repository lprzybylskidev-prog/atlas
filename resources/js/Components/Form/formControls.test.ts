import { describe, expect, it } from 'vitest';

const vueFiles = import.meta.glob('../../**/*.vue', {
    eager: true,
    import: 'default',
    query: '?raw',
}) as Record<string, string>;

describe('shared form control guardrails', () => {
    it('keeps native form controls inside shared form primitives', () => {
        for (const [file, contents] of Object.entries(vueFiles)) {
            if (file.startsWith('./') || file.includes('/Components/Form/')) {
                continue;
            }

            expect(contents, file).not.toMatch(/<(input|select|textarea)\b/);
        }
    });

    it('keeps ordinary pages on the shared AtlasForm submit wrapper', () => {
        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!file.includes('/Pages/')) {
                continue;
            }

            expect(contents, file).not.toMatch(/<form\b/);
            expect(contents, file).not.toMatch(/<button\s+type="submit"/);
        }
    });

    it('uses dedicated date and datetime primitives instead of generic FormInput types', () => {
        for (const [file, contents] of Object.entries(vueFiles)) {
            if (file.includes('/Components/Form/FormInput.vue')) {
                continue;
            }

            expect(contents, file).not.toMatch(/<FormInput\b[^>]*\btype="(?:date|datetime-local)"/);
        }
    });
});
