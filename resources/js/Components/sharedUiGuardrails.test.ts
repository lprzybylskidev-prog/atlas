import { describe, expect, it } from 'vitest';

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

describe('shared UI guardrails', () => {
    it('does not use native browser alert or confirm APIs', () => {
        for (const [file, contents] of Object.entries({ ...vueFiles, ...tsFiles })) {
            expect(contents, file).not.toMatch(/\bwindow\.(alert|confirm)\s*\(/);
        }
    });

    it('does not use native title attributes for tooltips', () => {
        for (const [file, contents] of Object.entries(vueFiles)) {
            const domTitleAttributes = contents
                .split('\n')
                .filter((line) => /<([a-z][\w-]*)\b[^>]*(\s|:)title=/.test(line))
                .filter(
                    (line) =>
                        !line.includes('<Head') &&
                        !line.includes('<DataTable') &&
                        !line.includes('<AdminLayout') &&
                        !line.includes('<AppLayout'),
                );

            expect(domTitleAttributes, file).toEqual([]);
        }
    });

    it('keeps modal accessibility behavior wired in the shared host', () => {
        const modalHost = Object.entries(vueFiles).find(([file]) => file.endsWith('/ModalHost.vue'))?.[1];

        expect(modalHost).toBeDefined();
        expect(modalHost).toContain('aria-modal="true"');
        expect(modalHost).toContain('aria-labelledby');
        expect(modalHost).toContain('previousFocus.value?.focus()');
        expect(modalHost).toContain("event.key === 'Tab'");
    });

    it('keeps admin shell in English without rendering language switching controls', () => {
        const adminLayout = Object.entries(vueFiles).find(([file]) => file.endsWith('/Layouts/AdminLayout.vue'))?.[1];

        expect(adminLayout).toBeDefined();
        expect(adminLayout).toContain('mode="admin"');
        expect(adminLayout).toContain('ui-locale="en"');
        expect(adminLayout).toContain(':show-locale-switcher="false"');
    });
});
