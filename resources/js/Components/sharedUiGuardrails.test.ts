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

const translationFiles = import.meta.glob('../../../lang/*.json', {
    eager: true,
    import: 'default',
    query: '?raw',
}) as Record<string, string>;

describe('shared UI guardrails', () => {
    it('keeps draft filter values isolated from applied table, export, and result state', () => {
        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!contents.includes('<FilterPanel')) {
                continue;
            }

            const resultConsumersUsingDraftFilters = Array.from(
                contents.matchAll(/<(?:DataTable|DataTableExportMenu)\b[^>]*:filters="filters"/g),
            ).map((match) => match[0]);

            expect(resultConsumersUsingDraftFilters, file).toEqual([]);
            expect(contents, `${file}: applied table filters must not be copied from the mutable form draft.`).not.toMatch(
                /const tableFilters = computed\(\(\) => \(\{ \.\.\.filters\.value \}\)\)/,
            );
            expect(contents, `${file}: result-state predicates must use applied filters.`).not.toMatch(
                /const \w*Missing = computed\(\(\) => filters\.value\./,
            );
        }
    });

    it('keeps work-time metric tiles section-specific and shared across user, manager, and administrator reports', () => {
        const metrics = Object.entries(vueFiles).find(([file]) => file.endsWith('/TimeTracking/TimeTrackingReportMetrics.vue'))?.[1] ?? '';
        const userReport = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/TimeTracking/UserReport.vue'))?.[1] ?? '';
        const adminOperations =
            Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/TimeTracking/AdminOperations.vue'))?.[1] ?? '';

        expect(metrics).toContain("props.section === 'other_work'");
        expect(metrics).toContain("props.section === 'breaks'");
        expect(metrics).toContain("props.section === 'corrections'");
        expect(metrics).toContain("props.section === 'work_sessions'");
        expect(metrics).toContain('props.summary.records');
        expect(metrics).toContain('props.summary.users');
        expect(metrics).toContain('props.summary.otherWorkSeconds');
        expect(userReport).toContain('<TimeTrackingReportMetrics :section="section" :summary="summary" />');
        expect(adminOperations).toContain('<TimeTrackingReportMetrics');
        expect(adminOperations).toContain('multi-user');
        expect(userReport).not.toContain('<OperationalMetricTile');
        expect(adminOperations).not.toContain('<OperationalMetricTile');
    });

    it('does not use native browser alert or confirm APIs', () => {
        for (const [file, contents] of Object.entries({ ...vueFiles, ...tsFiles })) {
            if (file.includes('/Guardrails/')) continue;
            expect(contents, file).not.toMatch(/\bwindow\.(alert|confirm)\s*\(/);
        }
    });

    it('does not use native title attributes for tooltips', () => {
        for (const [file, contents] of Object.entries(vueFiles)) {
            const domTitleAttributes = contents
                .split('\n')
                .filter((line) => /<([a-z][\w-]*)\b[^>]*(\s|:)title=/.test(line))
                .filter((line) => !line.includes('<Head') && !line.includes('<DataTable') && !line.includes('<AppLayout'));

            expect(domTitleAttributes, file).toEqual([]);
        }
    });

    it('keeps common technical filter and status labels human-readable', () => {
        const polishTranslations = translationFiles['../../../lang/pl.json'];
        const statusCatalog = Object.entries(tsFiles).find(([file]) => file.endsWith('/Services/statusCatalog.ts'))?.[1];

        expect(polishTranslations).toBeDefined();
        expect(polishTranslations).not.toMatch(/Dowoln|Dowolnie/);
        expect(polishTranslations).toContain('"pages.admin.files.filters.any_handling": "Wszystkie statusy obsługi"');
        expect(polishTranslations).toContain('"pages.admin.files.filters.not_applicable": "Nie wymaga obsługi"');
        expect(polishTranslations).toContain('"pages.admin.files.providers.fake": "Skaner testowy"');
        expect(polishTranslations).toContain('"pages.admin.managed_processes.filters.ok": "Nie wymaga obsługi"');
        expect(statusCatalog).toBeDefined();
        expect(statusCatalog).toContain("'half_open'");
        expect(statusCatalog).toContain("'under_review'");
        expect(statusCatalog).toContain('`datatable.status.${token}`');
    });

    it('keeps modal accessibility behavior wired in the shared host', () => {
        const modalHost = Object.entries(vueFiles).find(([file]) => file.endsWith('/ModalHost.vue'))?.[1];

        expect(modalHost).toBeDefined();
        expect(modalHost).toContain('aria-modal="true"');
        expect(modalHost).toContain('aria-labelledby');
        expect(modalHost).toContain('previousFocus.value?.focus()');
        expect(modalHost).toContain("event.key === 'Tab'");
    });

    it('keeps admin pages on the shared application shell', () => {
        const appLayout = Object.entries(vueFiles).find(([file]) => file.endsWith('/Layouts/AppLayout.vue'))?.[1];
        const adminLayout = Object.entries(vueFiles).find(([file]) => file.endsWith('/Layouts/AdminLayout.vue'))?.[1];

        expect(appLayout).toBeDefined();
        expect(adminLayout).toBeUndefined();
        expect(appLayout).not.toMatch(/\bui-locale="en"/);

        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!file.includes('/Pages/Admin/') && !file.includes('/Components/ManagedProcesses/')) {
                continue;
            }

            expect(contents, `${file}: admin surfaces must not reintroduce an AdminLayout wrapper.`).not.toContain('AdminLayout');

            if (file.includes('/Pages/Admin/ManagedProcesses/')) {
                expect(contents, `${file}: managed-process pages may use the shared section area only.`).toContain('ManagedProcessArea');

                continue;
            }

            expect(contents, `${file}: admin surfaces must use AppLayout directly in admin mode.`).toContain('AppLayout');
            expect(contents, `${file}: admin surfaces must explicitly select the admin shell mode.`).toContain('mode="admin"');
        }
    });

    it('keeps application, user, manager, and admin side navigation separated', () => {
        const sidebar = Object.entries(vueFiles).find(([file]) => file.endsWith('/Sidebar.vue'))?.[1];
        const mobileNavigation = Object.entries(vueFiles).find(([file]) => file.endsWith('/MobileNavigation.vue'))?.[1];
        const registry = Object.entries(tsFiles).find(([file]) => file.endsWith('/Navigation/registry.ts'))?.[1];
        const dashboard = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Dashboard.vue'))?.[1];
        const userPanel = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/User/Panel.vue'))?.[1];
        const managerPanel = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Manager/Panel.vue'))?.[1];
        const userReport = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/TimeTracking/UserReport.vue'))?.[1];
        const managerOperations = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/TimeTracking/AdminOperations.vue'))?.[1];
        const notifications = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Notifications/Index.vue'))?.[1];
        const polishTranslations = JSON.parse(translationFiles['../../../lang/pl.json'] ?? '{}') as Record<string, string>;
        const englishTranslations = JSON.parse(translationFiles['../../../lang/en.json'] ?? '{}') as Record<string, string>;

        expect(sidebar).toBeDefined();
        expect(sidebar).toContain('groups: NavigationGroup[]');
        expect(mobileNavigation).toBeDefined();
        expect(mobileNavigation).toContain('groups: NavigationGroup[]');
        expect(registry).toBeDefined();
        expect(registry).toContain('const groups: NavigationGroupDefinition[]');
        expect(registry).toContain('const modeDefinitions:');
        expect(sidebar).not.toContain("href: '/admin");
        expect(mobileNavigation).not.toContain("href: '/admin");
        expect(dashboard).toBeDefined();
        expect(dashboard).toContain('<AppLayout :title="t(\'pages.dashboard.title\')" :title-icon="IconLayoutDashboard" />');
        expect(polishTranslations['pages.dashboard.title']).toBe(polishTranslations['navigation.app_dashboard']);
        expect(polishTranslations['pages.dashboard.head_title']).toBe(polishTranslations['navigation.app_dashboard']);
        expect(englishTranslations['pages.dashboard.title']).toBe(englishTranslations['navigation.app_dashboard']);
        expect(englishTranslations['pages.dashboard.head_title']).toBe(englishTranslations['navigation.app_dashboard']);
        expect(userPanel).toContain('mode="user"');
        expect(userReport).toContain('mode="user"');
        expect(notifications).toContain('mode="user"');
        expect(managerPanel).toContain('mode="manager"');
        expect(managerOperations).toContain(':mode="surface"');
    });

    it('keeps page titles aligned with canonical navigation labels', () => {
        const polishTranslations = JSON.parse(translationFiles['../../../lang/pl.json'] ?? '{}') as Record<string, string>;
        const englishTranslations = JSON.parse(translationFiles['../../../lang/en.json'] ?? '{}') as Record<string, string>;
        const titleContracts = [
            ['navigation.app_dashboard', ['pages.dashboard.title', 'pages.dashboard.head_title']],
            [
                'navigation.user_dashboard',
                [
                    'pages.user_panel.title',
                    'pages.user_panel.head_title',
                    'pages.user_panel.profile_title',
                    'pages.user_panel.profile_head_title',
                ],
            ],
            ['navigation.manager_dashboard', ['pages.manager_panel.title', 'pages.manager_panel.head_title']],
            ['navigation.notifications', ['pages.notifications.title', 'pages.notifications.head_title']],
            ['navigation.time_tracking', ['pages.time_tracking.user_report.title', 'pages.time_tracking.user_report.head_title']],
            ['navigation.admin_dashboard', ['pages.admin.dashboard.title']],
            ['navigation.users', ['pages.admin.users.index.title', 'pages.admin.users.index.head_title']],
            ['navigation.teams', ['pages.admin.teams.title', 'pages.admin.teams.head_title']],
            ['navigation.roles', ['pages.admin.roles.title', 'pages.admin.roles.head_title']],
            ['navigation.permissions', ['pages.admin.permissions.title', 'pages.admin.permissions.head_title']],
            ['navigation.packages', ['pages.admin.packages.title', 'pages.admin.packages.head_title']],
            ['navigation.audit_security', ['pages.admin.audit.title', 'pages.admin.audit.head_title']],
            ['navigation.modules', ['pages.admin.modules.title', 'pages.admin.modules.head_title']],
            ['navigation.managed_processes', ['pages.admin.managed_processes.title', 'pages.admin.managed_processes.head_title']],
            ['navigation.queues', ['pages.admin.queues.title', 'pages.admin.queues.head_title']],
            ['navigation.files', ['pages.admin.files.title', 'pages.admin.files.head_title']],
            ['navigation.privacy_retention', ['pages.admin.privacy_retention.title', 'pages.admin.privacy_retention.head_title']],
            ['navigation.logs', ['pages.admin.logs.title', 'pages.admin.logs.head_title']],
            ['navigation.feature_flags', ['pages.admin.feature_flags.title', 'pages.admin.feature_flags.head_title']],
            ['navigation.rate_limits', ['pages.admin.rate_limits.title', 'pages.admin.rate_limits.head_title']],
            ['navigation.integrations', ['pages.admin.integrations.title', 'pages.admin.integrations.head_title']],
            ['navigation.search', ['pages.admin.search.title', 'pages.admin.search.head_title']],
        ] as const;

        for (const translations of [polishTranslations, englishTranslations]) {
            for (const [navigationKey, pageTitleKeys] of titleContracts) {
                for (const pageTitleKey of pageTitleKeys) {
                    expect(translations[pageTitleKey], `${pageTitleKey} must match ${navigationKey}`).toBe(translations[navigationKey]);
                }
            }
        }
    });

    it('keeps the global impersonation banner localized', () => {
        const appLayout = Object.entries(vueFiles).find(([file]) => file.endsWith('/Layouts/AppLayout.vue'))?.[1];

        expect(appLayout).toBeDefined();
        expect(appLayout).toContain("t('pages.admin.impersonation.banner.text'");
        expect(appLayout).toContain("t('pages.admin.impersonation.banner.exit')");
        expect(appLayout).not.toContain('Impersonating');
        expect(appLayout).not.toContain('Exit impersonation');
    });

    it('keeps breadcrumbs text-only in the top bar', () => {
        const topBar = Object.entries(vueFiles).find(([file]) => file.endsWith('/TopBar.vue'))?.[1];
        const breadcrumbNav = topBar?.match(/<nav[\s\S]*:aria-label="t\('navigation\.aria\.breadcrumb'\)"[\s\S]*<\/nav>/)?.[0] ?? '';

        expect(topBar).toBeDefined();
        expect(breadcrumbNav).toContain('{{ breadcrumb.label }}');
        expect(breadcrumbNav).not.toContain('<component');
        expect(breadcrumbNav).not.toContain(':is=');
    });

    it('keeps system status detail labels localized', () => {
        const systemStatusCard = Object.entries(vueFiles).find(([file]) =>
            file.endsWith('/ComposableView/Elements/SystemStatusCard.vue'),
        )?.[1];

        expect(systemStatusCard).toBeDefined();
        expect(systemStatusCard).toContain("t('pages.admin.dashboard.system_status.version')");
        expect(systemStatusCard).toContain("t('pages.admin.dashboard.system_status.queues')");
        expect(systemStatusCard).not.toContain("label: 'Queues'");
        expect(systemStatusCard).not.toContain("label: 'Version'");
        expect(systemStatusCard).not.toContain("label: 'Environment'");
    });

    it('keeps TimeTracking activity and offline states in the shared application shell', () => {
        const appLayout = Object.entries(vueFiles).find(([file]) => file.endsWith('/Layouts/AppLayout.vue'))?.[1];
        const tracker = Object.entries(tsFiles).find(([file]) => file.endsWith('/Composables/useTimeTrackingActivityTracker.ts'))?.[1];

        expect(appLayout).toBeDefined();
        expect(appLayout).toContain("t('pages.time_tracking.activity_warning.title')");
        expect(appLayout).toContain("t('pages.time_tracking.offline.banner')");
        expect(appLayout).toContain('DialogPanel');
        expect(tracker).toBeDefined();
        expect(tracker).toContain('performance.now()');
        expect(tracker).toContain('BroadcastChannel');
        expect(tracker).toContain("window.addEventListener('offline'");
        expect(tracker).toContain("window.addEventListener('online'");
    });

    it('keeps TimeTracking transition and lock screens on the authentication shell', () => {
        const startOtherWork = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/TimeTracking/StartOtherWork.vue'))?.[1];
        const breakLock = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/TimeTracking/BreakLock.vue'))?.[1];
        const otherWorkLock = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/TimeTracking/OtherWorkLock.vue'))?.[1];

        expect(startOtherWork).toBeDefined();
        expect(breakLock).toBeDefined();
        expect(otherWorkLock).toBeDefined();
        expect(startOtherWork).toContain('AuthLayout');
        expect(breakLock).toContain('AuthLayout');
        expect(otherWorkLock).toContain('AuthLayout');
        expect(startOtherWork).not.toContain('AppLayout');
        expect(breakLock).not.toContain('AppLayout');
        expect(otherWorkLock).not.toContain('AppLayout');
    });

    it('keeps Admin TimeTracking Other-work categories outside the report index', () => {
        const adminOperations = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/TimeTracking/AdminOperations.vue'))?.[1];
        const categoriesIndex = Object.entries(vueFiles).find(([file]) =>
            file.endsWith('/Pages/TimeTracking/AdminOtherWorkCategories.vue'),
        )?.[1];
        const categoriesCreate = Object.entries(vueFiles).find(([file]) =>
            file.endsWith('/Pages/TimeTracking/AdminOtherWorkCategoryCreate.vue'),
        )?.[1];

        expect(adminOperations).toBeDefined();
        expect(categoriesIndex).toBeDefined();
        expect(categoriesCreate).toBeDefined();
        expect(adminOperations).not.toContain('categoryForm');
        expect(adminOperations).not.toContain("categoryForm.post('/admin/work-time/other-work/categories'");
        expect(categoriesCreate).toContain('function basePath(): string');
        expect(categoriesCreate).toContain('form.post(`${basePath()}/other-work/categories`');
    });

    it('does not turn managed-process progress events into toast storms', () => {
        const realtimeEvents = Object.entries(tsFiles).find(([file]) => file.endsWith('/Services/realtimeEvents.ts'))?.[1];

        expect(realtimeEvents).toBeDefined();
        expect(realtimeEvents).toContain("event.eventType === 'notification.created'");
        expect(realtimeEvents).not.toMatch(/event\.eventType === 'operation\.progress'[\s\S]{0,260}toast\.push/);
    });

    it('keeps Inertia pages lazy-loaded so admin screens do not inflate the initial bundle', () => {
        const appEntrypoint = Object.entries(tsFiles).find(([file]) => file.endsWith('/app.ts'))?.[1];

        expect(appEntrypoint).toBeDefined();
        expect(appEntrypoint).toContain("import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue')");
        expect(appEntrypoint).not.toMatch(/\.\/Pages\/\*\*\/\*\.vue['"`]\s*,\s*\{\s*eager:\s*true/);
    });

    it('keeps admin page content on the shared page width primitive', () => {
        const pageStack = Object.entries(vueFiles).find(([file]) => file.endsWith('/PageStack.vue'))?.[1];

        expect(pageStack).toBeDefined();
        expect(pageStack).toContain('w-full space-y-5');
        expect(pageStack).not.toContain('max-w-');
        expect(pageStack).not.toContain('width?:');

        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!file.includes('/Pages/')) {
                continue;
            }

            expect(contents, `${file}: PageStack must stay fluid; pages must not select width variants.`).not.toMatch(
                /<PageStack\b[^>]*\swidth=/,
            );
        }

        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!file.includes('/Pages/Admin/')) {
                continue;
            }

            expect(contents, `${file}: Admin pages must use PageStack for canonical content width and vertical rhythm.`).toContain(
                '<PageStack',
            );
            expect(contents, `${file}: Admin pages must import PageStack.`).toContain('PageStack.vue');
        }
    });

    it('keeps admin filter actions on the shared filter panel pattern', () => {
        const filterPanel = Object.entries(vueFiles).find(([file]) => file.endsWith('/FilterPanel.vue'))?.[1];

        expect(filterPanel).toBeDefined();
        expect(filterPanel).toContain("t('filters.title')");
        expect(filterPanel).toContain("t('filters.clear_all')");
        expect(filterPanel).toContain("emit('clearFilter', filter.key)");
        expect(filterPanel).toContain('tone="neutral"');
        expect(filterPanel).toContain(':icon="IconRefresh"');
        expect(filterPanel).toContain(':icon="IconFilter"');

        for (const [file, contents] of Object.entries(vueFiles)) {
            expect(contents, file).not.toMatch(/<FormButton\b[^>]*\svariant=/);
        }
    });

    it('keeps advanced form controls generic and shared', () => {
        const formColorPicker = Object.entries(vueFiles).find(([file]) => file.endsWith('/Form/FormColorPicker.vue'))?.[1];
        const formImageCropper = Object.entries(vueFiles).find(([file]) => file.endsWith('/Form/FormImageCropper.vue'))?.[1];
        const formMoneyInput = Object.entries(vueFiles).find(([file]) => file.endsWith('/Form/FormMoneyInput.vue'))?.[1];
        const forbiddenFeatureControlFileName =
            /(?:Debt|Case|User|Profile|Avatar|Team|Manager|Notification|TimeTracking|Module|Search|Queue|Audit|Integration)(?:Money|Euro|Currency|Color|Image|Date|DateTime|Tag|Autocomplete|Upload|Cropper|Picker|Input|Select|Textarea)\.vue$/;
        const forbiddenKnownBadNames = ['DebtEuroInput', 'AvatarColorPicker', 'AvatarImageCropper', 'ProfileUpload', 'CaseDatePicker'];

        expect(formColorPicker).toBeDefined();
        expect(formImageCropper).toBeDefined();
        expect(formMoneyInput).toBeDefined();

        for (const [file, contents] of Object.entries(vueFiles)) {
            if (file.includes('/Components/Form/')) {
                expect(file, file).not.toMatch(forbiddenFeatureControlFileName);
            }

            for (const componentName of forbiddenKnownBadNames) {
                expect(contents, file).not.toContain(componentName);
            }
        }
    });

    it('keeps pages composed from shared form and action primitives', () => {
        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!file.includes('/Pages/')) {
                continue;
            }

            expect(contents, `${file}: pages must use shared Form* controls instead of native controls.`).not.toMatch(
                /<(input|select|textarea)\b/,
            );
            expect(contents, `${file}: reusable filter helpers belong in Utils or Composables.`).not.toMatch(
                /function\s+(allOptions|readableToken|readableFilterOption)\b/,
            );
            expect(contents, `${file}: repeated action footers belong in FormActions or DialogFormActions.`).not.toContain(
                'mt-5 flex flex-wrap justify-end gap-2',
            );
        }
    });

    it('keeps every route-backed page on the accepted view contract', () => {
        const intentionalEmptyDashboards = new Set(['/Pages/Dashboard.vue', '/Pages/Manager/Panel.vue']);

        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!file.includes('/Pages/')) {
                continue;
            }

            expect(contents, `${file}: every Inertia page must own its browser title.`).toContain('<Head');

            if (file.endsWith('/Pages/Error.vue')) {
                expect(contents, `${file}: the global error surface must not pretend to be an authenticated page.`).not.toContain(
                    '<AppLayout',
                );

                continue;
            }

            const usesAppLayout = contents.includes('<AppLayout');
            const usesAuthLayout = contents.includes('<AuthLayout');
            const usesManagedProcessArea = contents.includes('<ManagedProcessArea');

            expect(
                Number(usesAppLayout) + Number(usesAuthLayout) + Number(usesManagedProcessArea),
                `${file}: every page must select exactly one accepted shell.`,
            ).toBe(1);

            if (usesAppLayout) {
                expect(contents, `${file}: authenticated page titles must be visible in AppLayout.`).toMatch(/<AppLayout[\s\S]*?:title=/);
                expect(contents, `${file}: authenticated page titles must use the canonical route icon.`).toMatch(
                    /<AppLayout[\s\S]*?:title-icon=/,
                );

                if (![...intentionalEmptyDashboards].some((suffix) => file.endsWith(suffix))) {
                    expect(contents, `${file}: non-empty authenticated pages must use PageStack.`).toContain('<PageStack');
                }
            }

            if (usesManagedProcessArea) {
                expect(contents, `${file}: managed-process pages must use PageStack.`).toContain('<PageStack');
            }

            expect(contents, `${file}: route-backed pages must not own shell subsection navigation.`).not.toContain('ShellSubnavigation');
            expect(contents, `${file}: route-backed pages must not own duplicate navigation definitions.`).not.toContain(
                'NavigationGroupDefinition',
            );
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

    it('keeps actions, DataTable responsibilities, and ordinary tables on the canonical contracts', () => {
        const recordActions = Object.entries(vueFiles).find(([file]) => file.endsWith('/RecordActions.vue'))?.[1];
        const dataTable = Object.entries(vueFiles).find(([file]) => file.endsWith('/DataTable.vue'))?.[1] ?? '';
        const dataTableController =
            Object.entries(tsFiles).find(([file]) => file.endsWith('/DataTable/useDataTableController.ts'))?.[1] ?? '';
        const dataTableResponsibilityGuard =
            Object.entries(tsFiles).find(([file]) => file.endsWith('/DataTable/tableResponsibilityGuard.ts'))?.[1] ?? '';
        const actionContract = Object.entries(tsFiles).find(([file]) => file.endsWith('/Types/actions.ts'))?.[1] ?? '';

        expect(recordActions).toBeUndefined();
        expect(dataTable).toContain('useDataTableController');
        expect(dataTable).toContain('DataTableSavedViewsMenu');
        expect(dataTable).toContain('DataTableStateRow');
        expect(dataTable).toContain('DataTablePagination');
        expect(dataTableController).toContain('createDataTableFormatting');
        expect(dataTableController).toContain('useDataTableActions');
        expect(dataTableController).toContain('useDataTableSavedViews');
        expect(dataTableController).toContain('tableQueryPayload');
        expect(dataTableController).toContain('buildSavedViewState');
        expect(dataTableController).toContain('selectedTableRowIds');
        expect(dataTableResponsibilityGuard).toContain('centralOwnershipPatterns');
        expect(dataTable).not.toMatch(/function\s+(?:applySavedView|runRowAction|persistState|selectAllFiltered)\b/);
        expect(dataTable).not.toContain('function bulkActionIcon');

        for (const field of [
            'semantic?',
            'placement?',
            'endpoint?',
            'method?',
            'navigation?',
            'permission?',
            'module?',
            'available?',
            'disabledReason?',
            'confirm?',
            'reason?',
            'optimistic?',
            'feedback?',
            'refresh?',
        ]) {
            expect(actionContract).toContain(field);
        }

        for (const [file, contents] of Object.entries(vueFiles)) {
            if (!file.includes('/Pages/')) continue;
            expect(contents, `${file}: normal tabular data must use DataTable.`).not.toContain('<table');
        }
    });

    it('keeps locale failures explicit and MFA requests in the shared network service', () => {
        const translator = Object.entries(tsFiles).find(([file]) => file.endsWith('/Localization/translator.ts'))?.[1] ?? '';
        const userPanel = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/User/Panel.vue'))?.[1] ?? '';

        expect(translator).toContain('[translation:${key}]');
        expect(translator).not.toContain('humanize');
        expect(userPanel).toContain('requestJson');
        expect(userPanel).not.toContain('fetch(');
        expect(userPanel).toContain('CodeViewer');
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
        const statusBadge = Object.entries(vueFiles).find(([file]) => file.endsWith('/StatusBadge.vue'))?.[1];
        const statusBadgeUtil = Object.entries(tsFiles).find(([file]) => file.endsWith('/statusBadge.ts'))?.[1];
        const iconTile = Object.entries(vueFiles).find(([file]) => file.endsWith('/IconTile.vue'))?.[1];
        const operationalTile = Object.entries(vueFiles).find(([file]) => file.endsWith('/OperationalTile.vue'))?.[1];
        const operationalMetricTile = Object.entries(vueFiles).find(([file]) => file.endsWith('/OperationalMetricTile.vue'))?.[1];
        const dialogPanel = Object.entries(vueFiles).find(([file]) => file.endsWith('/DialogPanel.vue'))?.[1];
        const modalHost = Object.entries(vueFiles).find(([file]) => file.endsWith('/ModalHost.vue'))?.[1];

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
        expect(codeViewer).toContain("wrapLines ? 'w-full min-w-0' : 'min-w-max'");
        expect(codeViewer).toContain("wrapLines ? 'whitespace-pre-wrap wrap-break-word' : 'whitespace-pre'");
        expect(uiState).toBeDefined();
        expect(uiState).toContain("size?: 'default' | 'compact'");
        expect(uiState).toContain("'loading-initial'");
        expect(uiState).toContain("'permission-denied'");
        expect(uiState).toContain("'module-unavailable'");
        expect(uiState).toContain("'offline'");
        expect(noticeBanner).toBeDefined();
        expect(noticeBanner).toContain("tone?: 'info' | 'success' | 'warning' | 'danger'");
        expect(statusBadge).toBeDefined();
        expect(statusBadge).toContain('value?: boolean | string');
        expect(statusBadge).toContain('icon?: Component');
        expect(statusBadgeUtil).toBeDefined();
        expect(statusBadgeUtil).toContain("export type StatusBadgeTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger'");
        expect(statusBadgeUtil).toContain('statusBadgeToneForToken');
        expect(iconTile).toBeDefined();
        expect(iconTile).toContain("type IconTileTone = 'teal' | 'sky' | 'emerald' | 'amber' | 'rose' | 'zinc'");
        expect(iconTile).toContain("type IconTileSize = 'sm' | 'md'");
        expect(operationalTile).toBeDefined();
        expect(operationalTile).toContain('StatusBadge');
        expect(operationalTile).toContain('Tooltip');
        expect(operationalMetricTile).toBeDefined();
        expect(dialogPanel).toBeDefined();
        expect(dialogPanel).toContain('aria-modal="true"');
        expect(dialogPanel).toContain('CardHeader');
        expect(modalHost).toBeDefined();
        expect(modalHost).toContain('CardHeader');

        for (const [file, contents] of Object.entries(vueFiles)) {
            const mayUseCardHeader =
                file.endsWith('/SurfaceCard.vue') ||
                file.endsWith('/SectionHeader.vue') ||
                file.endsWith('/DialogPanel.vue') ||
                file.endsWith('/ModalHost.vue');

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
        expect(appLayout).toContain(':subnavigation="navigation.subnavigation"');
        expect(topBar).toBeDefined();
        expect(topBar).toContain('ShellSubnavigation');
        expect(shellSubnavigation).toBeDefined();
        expect(shellSubnavigation).toContain('aria-current');

        for (const [file, contents] of Object.entries(vueFiles)) {
            expect(contents, file).not.toContain('ManagedProcessTabs');
        }
    });

    it('keeps navigation definitions in one registry and the mobile drawer accessible', () => {
        const appLayout = Object.entries(vueFiles).find(([file]) => file.endsWith('/Layouts/AppLayout.vue'))?.[1];
        const sidebar = Object.entries(vueFiles).find(([file]) => file.endsWith('/Sidebar.vue'))?.[1];
        const mobileNavigation = Object.entries(vueFiles).find(([file]) => file.endsWith('/MobileNavigation.vue'))?.[1];
        const topBar = Object.entries(vueFiles).find(([file]) => file.endsWith('/TopBar.vue'))?.[1];
        const registry = Object.entries(tsFiles).find(([file]) => file.endsWith('/Navigation/registry.ts'))?.[1];

        expect(registry).toBeDefined();
        expect(appLayout).toContain('resolveNavigationRegistry');
        expect(sidebar).toContain('groups: NavigationGroup[]');
        expect(mobileNavigation).toContain('groups: NavigationGroup[]');
        expect(topBar).toContain('modeLinks?: ShellModeLink[]');

        for (const [file, contents] of Object.entries(vueFiles)) {
            if (
                file.endsWith('/Layouts/AppLayout.vue') ||
                file.endsWith('/TopBar.vue') ||
                file.endsWith('/MobileNavigation.vue') ||
                file.endsWith('/ShellSubnavigation.vue')
            ) {
                continue;
            }

            expect(contents, `${file}: pages select a registered section instead of defining shell subnavigation.`).not.toContain(
                'ShellSubnavigationItem',
            );
            expect(contents, `${file}: pages must not pass local subnavigation arrays.`).not.toContain(':subnavigation=');
        }

        expect(mobileNavigation).toContain('role="dialog"');
        expect(mobileNavigation).toContain('aria-modal="true"');
        expect(mobileNavigation).toContain("event.key === 'Escape'");
        expect(mobileNavigation).toContain("event.key !== 'Tab'");
        expect(mobileNavigation).toContain('previouslyFocused?.focus()');
        expect(mobileNavigation).toContain('overflow-y-auto');
        expect(mobileNavigation).toContain('variant="stacked"');
        expect(mobileNavigation).toContain(':aria-current="entry.active ? \'page\' : undefined"');
    });

    it('keeps admin navigation limited to accepted entry points', () => {
        const sidebar = Object.entries(vueFiles).find(([file]) => file.endsWith('/Sidebar.vue'))?.[1];
        const mobileNavigation = Object.entries(vueFiles).find(([file]) => file.endsWith('/MobileNavigation.vue'))?.[1];
        const registry = Object.entries(tsFiles).find(([file]) => file.endsWith('/Navigation/registry.ts'))?.[1];

        expect(sidebar).toBeDefined();
        expect(mobileNavigation).toBeDefined();
        expect(registry).toBeDefined();

        for (const route of [
            'admin.system-status',
            'admin.users.index',
            'admin.teams.index',
            'admin.managed-processes.index',
            'admin.queues.index',
            'admin.files.index',
            'admin.privacy-retention.index',
            'admin.logs.index',
            'admin.feature-flags.index',
            'admin.rate-limits.index',
            'admin.pulse.view',
            'admin.telescope.view',
        ]) {
            expect(registry).toContain(`'${route}'`);
        }

        expect(registry).toContain("labelKey: 'navigation.group.identity_access'");
        expect(registry).toContain("labelKey: 'navigation.group.diagnostics'");
        expect(registry).toContain("labelKey: 'navigation.group.system_configuration'");
        expect(registry).toContain('external: true');
    });

    it('does not use settings icons as generic table action fallbacks', () => {
        const actionCatalog = Object.entries(tsFiles).find(([file]) => file.endsWith('/Services/actionCatalog.ts'))?.[1];

        expect(actionCatalog).toBeDefined();
        expect(actionCatalog).not.toContain('?? IconSettings');
        expect(actionCatalog).not.toContain('return IconSettings;');
        expect(actionCatalog).toContain("configure: 'update'");
        expect(actionCatalog).toContain("settings: 'update'");
        expect(actionCatalog).toContain('update: IconPencil');
    });

    it('keeps rebuilt Users workflow form buttons icon-led', () => {
        const userWorkflowSurfaces = Object.entries(vueFiles).filter(
            ([file]) =>
                file.includes('/Pages/Admin/Users/') || file.endsWith('/Components/Authorization/UserTeamAuthorizationWorkflow.vue'),
        );

        for (const [file, contents] of userWorkflowSurfaces) {
            const buttonsWithoutIcons = Array.from(contents.matchAll(/<FormButton\b([^>]*)>/g))
                .map((match) => match[0])
                .filter((button) => !/\s:?icon=/.test(button));

            expect(buttonsWithoutIcons, file).toEqual([]);
        }
    });

    it('keeps user-side and team-side authorization access in one workflow module', () => {
        const create = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Admin/Users/Create.vue'))?.[1];
        const edit = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Admin/Users/Edit.vue'))?.[1];
        const teamCreate = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Admin/Teams/Create.vue'))?.[1];
        const teamEdit = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Admin/Teams/Edit.vue'))?.[1];
        const workflow = Object.entries(vueFiles).find(([file]) => file.endsWith('/Authorization/UserTeamAuthorizationWorkflow.vue'))?.[1];

        expect(create).toBeDefined();
        expect(edit).toBeDefined();
        expect(workflow).toBeDefined();

        expect(teamCreate).toBeDefined();
        expect(teamEdit).toBeDefined();
        expect(create).toContain('UserTeamAuthorizationWorkflow');
        expect(edit).toContain('UserTeamAuthorizationWorkflow');
        expect(teamCreate).toContain('UserTeamAuthorizationWorkflow');
        expect(teamEdit).toContain('UserTeamAuthorizationWorkflow');
        expect(create).not.toContain('sourceOptions');
        expect(edit).not.toContain('sourceOptions');
        expect(workflow).toContain('const sourceOptions = computed');
        expect(workflow).toContain('packageOptionsForAssignment');
        expect(workflow).toContain('copySourceOptionsForAssignment');
        expect(workflow).toContain("mode: 'create' | 'edit'");
        expect(workflow).toContain("contextAxis?: 'user' | 'team'");
        expect(workflow).toContain(':aria-expanded="expandedIndex === index"');
        expect(workflow).toContain('roleGrantsByPermission');
        expect(workflow).toContain('checked: true');
        expect(workflow).toContain('disabled: true');
        expect(workflow).toContain('granted_by_roles');
        expect(workflow).not.toContain('provenance_diverged');
        expect(workflow).not.toContain('Original source');
    });

    it('keeps rebuilt Users actions and sensitivity options shared', () => {
        const index = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Admin/Users/Index.vue'))?.[1];
        const edit = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Admin/Users/Edit.vue'))?.[1];
        const actions = Object.entries(tsFiles).find(([file]) => file.endsWith('/Composables/useAdminUserAccountActions.ts'))?.[1];
        const sensitivity = Object.entries(tsFiles).find(([file]) => file.endsWith('/Composables/useAccountSensitivityOptions.ts'))?.[1];

        expect(index).toBeDefined();
        expect(edit).toBeDefined();
        expect(actions).toBeDefined();
        expect(sensitivity).toBeDefined();

        expect(index).toContain('useAdminUserAccountActions');
        expect(edit).toContain('useAdminUserAccountActions');
        expect(index).toContain('useAccountSensitivityOptions');
        expect(edit).toContain('useAccountSensitivityOptions');
        expect(actions).toContain('accountActionDefinitions');
        expect(sensitivity).toContain('accountSensitivityValues');
    });

    it('keeps admin presentation limited to accepted sidebar workflow rebuilds', () => {
        const adminPages = Object.keys(vueFiles)
            .filter((file) => file.includes('/Pages/Admin/'))
            .sort();

        expect(adminPages).toEqual([
            '../Pages/Admin/Audit/ImpersonationSession.vue',
            '../Pages/Admin/Audit/Index.vue',
            '../Pages/Admin/Audit/SecurityHistory.vue',
            '../Pages/Admin/Authorization/Packages.vue',
            '../Pages/Admin/Authorization/Packages/Create.vue',
            '../Pages/Admin/Authorization/Packages/Edit.vue',
            '../Pages/Admin/Authorization/Permissions.vue',
            '../Pages/Admin/Authorization/Roles.vue',
            '../Pages/Admin/Authorization/Roles/Create.vue',
            '../Pages/Admin/Authorization/Roles/Edit.vue',
            '../Pages/Admin/FeatureFlags/Index.vue',
            '../Pages/Admin/Files/Index.vue',
            '../Pages/Admin/Impersonation/Start.vue',
            '../Pages/Admin/Integrations/Index.vue',
            '../Pages/Admin/Logs/Index.vue',
            '../Pages/Admin/ManagedProcesses/Definitions.vue',
            '../Pages/Admin/ManagedProcesses/Runs.vue',
            '../Pages/Admin/ManagedProcesses/Schedules.vue',
            '../Pages/Admin/ManagedProcesses/Schedules/Create.vue',
            '../Pages/Admin/ManagedProcesses/Show.vue',
            '../Pages/Admin/Modules/Index.vue',
            '../Pages/Admin/Modules/Show.vue',
            '../Pages/Admin/Modules/TeamConfiguration.vue',
            '../Pages/Admin/PrivacyRetention/Index.vue',
            '../Pages/Admin/PrivacyRetention/LegalHoldCreate.vue',
            '../Pages/Admin/PrivacyRetention/LegalHolds.vue',
            '../Pages/Admin/PrivacyRetention/Operations.vue',
            '../Pages/Admin/Queues/Index.vue',
            '../Pages/Admin/RateLimits/Index.vue',
            '../Pages/Admin/Search/Index.vue',
            '../Pages/Admin/SystemStatus.vue',
            '../Pages/Admin/Teams/Create.vue',
            '../Pages/Admin/Teams/Edit.vue',
            '../Pages/Admin/Teams/Index.vue',
            '../Pages/Admin/Teams/Structure.vue',
            '../Pages/Admin/Users/Create.vue',
            '../Pages/Admin/Users/Edit.vue',
            '../Pages/Admin/Users/Index.vue',
        ]);
    });
});
