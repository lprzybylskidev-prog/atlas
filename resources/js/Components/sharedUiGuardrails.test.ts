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

    it('keeps page action links and form footers on shared primitives', () => {
        const actionLink = Object.entries(vueFiles).find(([file]) => file.endsWith('/ActionLink.vue'))?.[1];
        const formActions = Object.entries(vueFiles).find(([file]) => file.endsWith('/FormActions.vue'))?.[1];

        expect(actionLink).toBeDefined();
        expect(actionLink).toContain("tone?: 'primary' | 'neutral'");
        expect(actionLink).toContain('focus-visible:outline-amber-500');
        expect(formActions).toBeDefined();
        expect(formActions).toContain('flex flex-wrap items-center gap-2');

        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!file.includes('/Pages/')) {
                continue;
            }

            expect(contents, file).not.toMatch(/<Link[\s\S]{0,240}class="inline-flex h-10/);
        }
    });

    it('keeps shared surface cards and technical viewers on shared primitives', () => {
        const surfaceCard = Object.entries(vueFiles).find(([file]) => file.endsWith('/SurfaceCard.vue'))?.[1];
        const shellNamedCard = Object.entries(vueFiles).find(([file]) => file.endsWith('/AdminCard.vue'))?.[1];
        const cardHeader = Object.entries(vueFiles).find(([file]) => file.endsWith('/CardHeader.vue'))?.[1];
        const sectionHeader = Object.entries(vueFiles).find(([file]) => file.endsWith('/SectionHeader.vue'))?.[1];
        const checkboxList = Object.entries(vueFiles).find(([file]) => file.endsWith('/CheckboxList.vue'))?.[1];
        const codeViewer = Object.entries(vueFiles).find(([file]) => file.endsWith('/CodeViewer.vue'))?.[1];
        const uiState = Object.entries(vueFiles).find(([file]) => file.endsWith('/UiState.vue'))?.[1];
        const noticeBanner = Object.entries(vueFiles).find(([file]) => file.endsWith('/NoticeBanner.vue'))?.[1];
        const textBadge = Object.entries(vueFiles).find(([file]) => file.endsWith('/TextBadge.vue'))?.[1];
        const iconTile = Object.entries(vueFiles).find(([file]) => file.endsWith('/IconTile.vue'))?.[1];
        const dialogPanel = Object.entries(vueFiles).find(([file]) => file.endsWith('/DialogPanel.vue'))?.[1];

        expect(shellNamedCard).toBeUndefined();
        expect(surfaceCard).toBeDefined();
        expect(surfaceCard).toContain('CardHeader');
        expect(surfaceCard).toContain("iconVariant: 'secondary'");
        expect(surfaceCard).toContain('border-b border-zinc-200 bg-zinc-50 px-4 py-3');
        expect(surfaceCard).toContain('dark:border-zinc-800 dark:bg-zinc-900/60');
        expect(cardHeader).toBeDefined();
        expect(cardHeader).toContain("iconVariant?: 'main' | 'secondary' | 'none'");
        expect(cardHeader).toContain("iconVariant === 'main' ? 'h-11 w-11' : 'h-9 w-9'");
        expect(sectionHeader).toBeDefined();
        expect(sectionHeader).toContain('CardHeader');
        expect(sectionHeader).toContain('icon: Component;');
        expect(checkboxList).toBeDefined();
        expect(checkboxList).toContain('max-h-56');
        expect(checkboxList).toContain('itemMonospace');
        expect(codeViewer).toBeDefined();
        expect(codeViewer).toContain("language?: 'json' | 'log' | 'stack' | 'text' | 'toml'");
        expect(codeViewer).toContain('font-mono text-xs leading-5');
        expect(uiState).toBeDefined();
        expect(uiState).toContain("size?: 'default' | 'compact'");
        expect(uiState).toContain("variant: 'loading' | 'empty' | 'error' | 'no-results'");
        expect(noticeBanner).toBeDefined();
        expect(noticeBanner).toContain("tone?: 'info' | 'success' | 'warning' | 'danger'");
        expect(textBadge).toBeDefined();
        expect(textBadge).toContain("type TextBadgeTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger'");
        expect(textBadge).toContain('icon?: Component');
        expect(iconTile).toBeDefined();
        expect(iconTile).toContain("type IconTileTone = 'teal' | 'sky' | 'emerald' | 'amber' | 'rose' | 'zinc'");
        expect(iconTile).toContain("type IconTileSize = 'sm' | 'md'");
        expect(dialogPanel).toBeDefined();
        expect(dialogPanel).toContain('aria-modal="true"');
        expect(dialogPanel).toContain('IconTile');

        for (const [file, contents] of Object.entries(vueFiles)) {
            const mayUseCardHeader = file.endsWith('/SurfaceCard.vue') || file.endsWith('/SectionHeader.vue');

            if (mayUseCardHeader) {
                continue;
            }

            expect(contents, file).not.toContain('<CardHeader');
        }

        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!file.includes('/Pages/')) {
                continue;
            }

            const localCardShells = contents
                .split('\n')
                .filter((line) => line.includes('rounded-lg border border-zinc-200 bg-white'))
                .filter((line) => !line.includes('relative w-full max-w-xl'));

            expect(localCardShells, file).toEqual([]);
            expect(contents, file).not.toContain('<CardHeader');
            expect(contents, file).not.toContain('<pre');
            expect(contents, file).not.toContain('aria-modal="true"');
            expect(contents, file).not.toMatch(
                /<FormCheckbox[\s\S]{0,300}(role_names|direct_permission_names|form\.permissions|form\.direct_permissions|form\.initial_roles)/,
            );
        }
    });

    it('keeps page surface card headers explicit and icon-backed', () => {
        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!file.includes('/Pages/')) {
                continue;
            }

            const surfaceCardTags = contents.match(/<SurfaceCard\b[\s\S]*?>/g) ?? [];

            for (const tag of surfaceCardTags) {
                const hasTitle = /(\s|:)title=/.test(tag);
                const hasIcon = /(\s|:)icon=/.test(tag);
                const suppressesIcon = /icon-variant="none"|:icon-variant="'none'"/.test(tag);
                const hasAccessibleAnonymousLabel = /(\s|:)aria-label=/.test(tag);

                if (hasTitle) {
                    expect(
                        hasIcon || suppressesIcon,
                        `${file}: titled SurfaceCard must provide an icon or explicitly suppress it.\n${tag}`,
                    ).toBe(true);

                    continue;
                }

                expect(
                    hasAccessibleAnonymousLabel,
                    `${file}: anonymous SurfaceCard must be a deliberate labeled wrapper, not an accidental headerless card.\n${tag}`,
                ).toBe(true);
            }
        }
    });

    it('keeps module subsection navigation in the shell instead of page cards', () => {
        const appLayout = Object.entries(vueFiles).find(([file]) => file.endsWith('/Layouts/AppLayout.vue'))?.[1];
        const topBar = Object.entries(vueFiles).find(([file]) => file.endsWith('/TopBar.vue'))?.[1];
        const shellSubnavigation = Object.entries(vueFiles).find(([file]) => file.endsWith('/ShellSubnavigation.vue'))?.[1];

        expect(appLayout).toBeDefined();
        expect(appLayout).toContain(':subnavigation="subnavigation"');
        expect(topBar).toBeDefined();
        expect(topBar).toContain('ShellSubnavigation');
        expect(shellSubnavigation).toBeDefined();
        expect(shellSubnavigation).toContain('aria-current');

        for (const [file, contents] of Object.entries(vueFiles)) {
            expect(contents, file).not.toContain('ManagedProcessTabs');
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
