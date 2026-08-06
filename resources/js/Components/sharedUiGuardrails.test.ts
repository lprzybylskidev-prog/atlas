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
                .filter((line) => !line.includes('<Head') && !line.includes('<DataTable') && !line.includes('<AppLayout'));

            expect(domTitleAttributes, file).toEqual([]);
        }
    });

    it('keeps common technical filter and status labels human-readable', () => {
        const polishTranslations = translationFiles['../../../lang/pl.json'];
        const dataTable = Object.entries(vueFiles).find(([file]) => file.endsWith('/DataTable.vue'))?.[1];

        expect(polishTranslations).toBeDefined();
        expect(polishTranslations).not.toMatch(/Dowoln|Dowolnie/);
        expect(polishTranslations).toContain('"pages.admin.files.filters.any_handling": "Wszystkie statusy obsługi"');
        expect(polishTranslations).toContain('"pages.admin.files.filters.not_applicable": "Nie wymaga obsługi"');
        expect(polishTranslations).toContain('"pages.admin.files.providers.fake": "Skaner testowy"');
        expect(polishTranslations).toContain('"pages.admin.managed_processes.filters.ok": "Nie wymaga obsługi"');
        expect(dataTable).toBeDefined();
        expect(dataTable).toContain("half_open: 'datatable.status.half_open'");
        expect(dataTable).toContain("under_review: 'datatable.status.under_review'");
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
        const dashboard = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Dashboard.vue'))?.[1];
        const userPanel = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/User/Panel.vue'))?.[1];
        const managerPanel = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Manager/Panel.vue'))?.[1];
        const userReport = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/TimeTracking/UserReport.vue'))?.[1];
        const managerReport = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/TimeTracking/ManagerReport.vue'))?.[1];
        const notifications = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Notifications/Index.vue'))?.[1];
        const polishTranslations = JSON.parse(translationFiles['../../../lang/pl.json'] ?? '{}') as Record<string, string>;
        const englishTranslations = JSON.parse(translationFiles['../../../lang/en.json'] ?? '{}') as Record<string, string>;

        expect(sidebar).toBeDefined();
        expect(sidebar).toContain("const workspaceItemsByMode: Record<Exclude<ShellMode, 'admin'>, NavigationNode[]>");
        expect(sidebar).toContain('app: [appDashboard]');
        expect(sidebar).toContain('user: [');
        expect(sidebar).toContain('manager: [');
        expect(sidebar).not.toContain('v-if="mode !== \'admin\'"');
        expect(mobileNavigation).toBeDefined();
        expect(mobileNavigation).toContain("const workspaceItemsByMode: Record<Exclude<ShellMode, 'admin'>, MobileNavigationItem[]>");
        expect(mobileNavigation).toContain('app: [appDashboard]');
        expect(mobileNavigation).not.toContain('v-if="mode !== \'admin\'"');
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
        expect(managerReport).toContain('mode="manager"');
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
            [
                'navigation.time_tracking_manager',
                ['pages.time_tracking.manager_report.title', 'pages.time_tracking.manager_report.head_title'],
            ],
            ['navigation.admin_dashboard', ['pages.admin.dashboard.title']],
            ['navigation.users', ['pages.admin.users.index.title', 'pages.admin.users.index.head_title']],
            ['navigation.teams', ['pages.admin.teams.title', 'pages.admin.teams.head_title']],
            ['navigation.managers', ['pages.admin.managers.title', 'pages.admin.managers.head_title']],
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
        expect(filterPanel).toContain("title: 'Filters'");
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
        expect(codeViewer).toContain("wrapLines ? 'whitespace-pre-wrap break-words' : 'whitespace-pre'");
        expect(uiState).toBeDefined();
        expect(uiState).toContain("size?: 'default' | 'compact'");
        expect(uiState).toContain("variant: 'loading' | 'empty' | 'error' | 'no-results'");
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
        expect(appLayout).toContain(':subnavigation="subnavigation"');
        expect(topBar).toBeDefined();
        expect(topBar).toContain('ShellSubnavigation');
        expect(shellSubnavigation).toBeDefined();
        expect(shellSubnavigation).toContain('aria-current');

        for (const [file, contents] of Object.entries(vueFiles)) {
            expect(contents, file).not.toContain('ManagedProcessTabs');
        }
    });

    it('keeps admin navigation limited to accepted entry points', () => {
        const sidebar = Object.entries(vueFiles).find(([file]) => file.endsWith('/Sidebar.vue'))?.[1];
        const mobileNavigation = Object.entries(vueFiles).find(([file]) => file.endsWith('/MobileNavigation.vue'))?.[1];

        expect(sidebar).toBeDefined();
        expect(mobileNavigation).toBeDefined();

        for (const contents of [sidebar, mobileNavigation]) {
            expect(contents).toContain("canSeeAdminRoute('admin.system-status')");
            expect(contents).toContain("canSeeAdminRoute('admin.users.index')");
            expect(contents).toContain("canSeeAdminRoute('admin.teams.index')");
            expect(contents).toContain("canSeeAdminRoute('admin.managed-processes.index')");
            expect(contents).toContain("canSeeAdminRoute('admin.queues.index')");
            expect(contents).toContain("canSeeAdminRoute('admin.files.index')");
            expect(contents).toContain("canSeeAdminRoute('admin.privacy-retention.index')");
            expect(contents).toContain("canSeeAdminRoute('admin.logs.index')");
            expect(contents).toContain("canSeeAdminRoute('admin.feature-flags.index')");
            expect(contents).toContain("canSeeAdminRoute('admin.rate-limits.index')");
            expect(contents).toContain("canSeeAdminRoute('admin.pulse.view')");
            expect(contents).toContain("canSeeAdminRoute('admin.telescope.view')");
            expect(contents).toContain("t('navigation.group.identity_access')");
            expect(contents).toContain("t('navigation.group.diagnostics')");
            expect(contents).toContain("t('navigation.group.system_configuration')");
            expect(contents).not.toContain("t('navigation.group.operations')");
            expect(contents).toContain("t('navigation.pulse')");
            expect(contents).toContain("t('navigation.telescope')");
            expect(contents).toContain('external: true');
        }
    });

    it('does not use settings icons as generic table action fallbacks', () => {
        const dataTable = Object.entries(vueFiles).find(([file]) => file.endsWith('/DataTable.vue'))?.[1];

        expect(dataTable).toBeDefined();
        expect(dataTable).not.toContain('?? IconSettings');
        expect(dataTable).not.toContain('return IconSettings;');
        expect(dataTable).toContain('configure: IconSettings');
        expect(dataTable).toContain('settings: IconSettings');
    });

    it('keeps rebuilt Users workflow form buttons icon-led', () => {
        const userWorkflowSurfaces = Object.entries(vueFiles).filter(
            ([file]) => file.includes('/Pages/Admin/Users/') || file.endsWith('/Components/Users/UserTeamAccessWorkflow.vue'),
        );

        for (const [file, contents] of userWorkflowSurfaces) {
            const buttonsWithoutIcons = Array.from(contents.matchAll(/<FormButton\b([^>]*)>/g))
                .map((match) => match[0])
                .filter((button) => !/\s:?icon=/.test(button));

            expect(buttonsWithoutIcons, file).toEqual([]);
        }
    });

    it('keeps rebuilt Users team access in one workflow module', () => {
        const create = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Admin/Users/Create.vue'))?.[1];
        const edit = Object.entries(vueFiles).find(([file]) => file.endsWith('/Pages/Admin/Users/Edit.vue'))?.[1];
        const workflow = Object.entries(vueFiles).find(([file]) => file.endsWith('/Users/UserTeamAccessWorkflow.vue'))?.[1];

        expect(create).toBeDefined();
        expect(edit).toBeDefined();
        expect(workflow).toBeDefined();

        expect(create).toContain('UserTeamAccessWorkflow');
        expect(edit).toContain('UserTeamAccessWorkflow');
        expect(create).not.toContain('sourceOptions');
        expect(edit).not.toContain('sourceOptions');
        expect(workflow).toContain('const sourceOptions = computed');
        expect(workflow).toContain('packageOptionsForAssignment');
        expect(workflow).toContain('copySourceOptionsForAssignment');
        expect(workflow).toContain("mode: 'create' | 'edit'");
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
            '../Pages/Admin/Managers/Create.vue',
            '../Pages/Admin/Managers/Edit.vue',
            '../Pages/Admin/Managers/Index.vue',
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
            '../Pages/Admin/Users/Create.vue',
            '../Pages/Admin/Users/Edit.vue',
            '../Pages/Admin/Users/Index.vue',
        ]);
    });
});
