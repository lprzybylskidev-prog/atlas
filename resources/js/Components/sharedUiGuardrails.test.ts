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
        const filterPanel = Object.entries(vueFiles).find(([file]) => file.endsWith('/FilterPanel.vue'))?.[1];

        expect(filterPanel).toBeDefined();
        expect(filterPanel).toContain("title: 'Filters'");
        expect(filterPanel).toContain('tone="neutral"');
        expect(filterPanel).toContain(':icon="IconRefresh"');
        expect(filterPanel).toContain(':icon="IconFilter"');

        for (const [file, contents] of Object.entries(vueFiles)) {
            expect(contents, file).not.toMatch(/<FormButton\b[^>]*\svariant=/);
        }
    });

    it('keeps admin page action links and form footers on shared primitives', () => {
        const actionLink = Object.entries(vueFiles).find(([file]) => file.endsWith('/ActionLink.vue'))?.[1];
        const formActions = Object.entries(vueFiles).find(([file]) => file.endsWith('/FormActions.vue'))?.[1];

        expect(actionLink).toBeDefined();
        expect(actionLink).toContain("tone?: 'primary' | 'neutral'");
        expect(actionLink).toContain('focus-visible:outline-amber-500');
        expect(formActions).toBeDefined();
        expect(formActions).toContain('flex flex-wrap items-center gap-2');

        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!file.includes('/Pages/Admin/')) {
                continue;
            }

            expect(contents, file).not.toMatch(/<Link[\s\S]{0,240}class="inline-flex h-10/);
        }
    });

    it('keeps admin cards and technical viewers on shared primitives', () => {
        const cardHeader = Object.entries(vueFiles).find(([file]) => file.endsWith('/CardHeader.vue'))?.[1];
        const checkboxList = Object.entries(vueFiles).find(([file]) => file.endsWith('/CheckboxList.vue'))?.[1];
        const codeViewer = Object.entries(vueFiles).find(([file]) => file.endsWith('/CodeViewer.vue'))?.[1];

        expect(cardHeader).toBeDefined();
        expect(cardHeader).toContain('text-sm font-semibold text-zinc-950');
        expect(cardHeader).not.toContain('h-9 w-9');
        expect(checkboxList).toBeDefined();
        expect(checkboxList).toContain('max-h-56');
        expect(checkboxList).toContain('itemMonospace');
        expect(codeViewer).toBeDefined();
        expect(codeViewer).toContain("language?: 'json' | 'log' | 'stack' | 'text' | 'toml'");
        expect(codeViewer).toContain('font-mono text-xs leading-5');

        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!file.includes('/Pages/Admin/')) {
                continue;
            }

            const localHeadings = contents
                .split('\n')
                .filter((line) => line.includes('<h2'))
                .filter((line) => !line.includes('rate-limit-reset-instructions-title'));

            expect(localHeadings, file).toEqual([]);
            expect(contents, file).not.toContain('<pre');
            expect(contents, file).not.toMatch(
                /<FormCheckbox[\s\S]{0,300}(role_names|direct_permission_names|form\.permissions|form\.direct_permissions|form\.initial_roles)/,
            );
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

    it('opens local Telescope navigation outside the Inertia shell', () => {
        const sidebar = Object.entries(vueFiles).find(([file]) => file.endsWith('/Sidebar.vue'))?.[1];
        const mobileNavigation = Object.entries(vueFiles).find(([file]) => file.endsWith('/MobileNavigation.vue'))?.[1];

        expect(sidebar).toBeDefined();
        expect(sidebar).toContain("key: 'oversight.telescope'");
        expect(sidebar).toContain("visible: canSeeAdminRoute('admin.telescope.view')");
        expect(sidebar).toContain('external: true');
        expect(mobileNavigation).toContain("t('navigation.telescope')");
        expect(mobileNavigation).toContain('external: true');
    });
});
