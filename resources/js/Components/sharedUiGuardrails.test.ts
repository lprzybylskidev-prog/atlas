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

    it('keeps admin filter actions on the shared filter panel pattern', () => {
        const adminFilterPanel = Object.entries(vueFiles).find(([file]) => file.endsWith('/AdminFilterPanel.vue'))?.[1];

        expect(adminFilterPanel).toBeDefined();
        expect(adminFilterPanel).toContain("title: 'Filters'");
        expect(adminFilterPanel).toContain('tone="neutral"');
        expect(adminFilterPanel).toContain(':icon="IconRefresh"');
        expect(adminFilterPanel).toContain(':icon="IconFilter"');

        for (const [file, contents] of Object.entries(vueFiles)) {
            expect(contents, file).not.toMatch(/<FormButton\b[^>]*\svariant=/);
        }
    });

    it('keeps admin page action links and form footers on shared primitives', () => {
        const adminActionLink = Object.entries(vueFiles).find(([file]) => file.endsWith('/AdminActionLink.vue'))?.[1];
        const adminFormActions = Object.entries(vueFiles).find(([file]) => file.endsWith('/AdminFormActions.vue'))?.[1];

        expect(adminActionLink).toBeDefined();
        expect(adminActionLink).toContain("tone?: 'primary' | 'neutral'");
        expect(adminActionLink).toContain('focus-visible:outline-amber-500');
        expect(adminFormActions).toBeDefined();
        expect(adminFormActions).toContain('flex flex-wrap items-center gap-2');

        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!file.includes('/Pages/Admin/')) {
                continue;
            }

            expect(contents, file).not.toMatch(/<Link[\s\S]{0,240}class="inline-flex h-10/);
        }
    });

    it('opens package-owned Pulse navigation outside the Inertia shell', () => {
        const sidebar = Object.entries(vueFiles).find(([file]) => file.endsWith('/Sidebar.vue'))?.[1];
        const sidebarNode = Object.entries(vueFiles).find(([file]) => file.endsWith('/SidebarNavNode.vue'))?.[1];
        const mobileNavigation = Object.entries(vueFiles).find(([file]) => file.endsWith('/MobileNavigation.vue'))?.[1];

        expect(sidebar).toBeDefined();
        expect(sidebar).toContain("key: 'oversight.pulse'");
        expect(sidebar).toContain('external: true');
        expect(sidebarNode).toContain(':target="node.external ? \'_blank\' : undefined"');
        expect(sidebarNode).toContain(':rel="node.external ? \'noopener noreferrer\' : undefined"');
        expect(mobileNavigation).toContain("t('navigation.pulse')");
        expect(mobileNavigation).toContain('external: true');
        expect(mobileNavigation).toContain(':target="item.external ? \'_blank\' : undefined"');
        expect(mobileNavigation).toContain(':rel="item.external ? \'noopener noreferrer\' : undefined"');
    });
});
