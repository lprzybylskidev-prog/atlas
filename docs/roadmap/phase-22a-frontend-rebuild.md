# Phase 22a — Frontend rebuild and design system hardening

**Status:** `complete`

## Objective

Rebuild the current Atlas frontend into a consistent, reusable, documented, and tested UI system before adding more Auth, application, or Admin workflows and modules.

Most current frontend surfaces are Admin screens, so the audit starts there, but the target is the whole frontend architecture: layouts, pages, shared components, tables, forms, filters, formatters, visual states, route visibility, and review rules. This phase is a deliberate frontend architecture repair. It must preserve the current Atlas visual direction where it is accepted, but replace page-local improvisation with shared UI primitives, documented usage rules, and verification that future views follow the same system.

## Dependencies

- [Phase 9 — Shared UI components](phase-09-shared-ui.md)
- [Phase 10 — Shared tables and saved views](phase-10-shared-tables-saved-views.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Phase 20b — Managed processes, process logs, and scheduler](phase-20b-managed-processes.md)
- [Phase 22 — Search](phase-22-search.md)
- [Frontend and shared UI architecture](../architecture/frontend-ui.md)
- [Tables, reports, exports, and print architecture](../architecture/tables-reports-exports-and-print.md)

## Related documentation

- Architecture: `../architecture/frontend-ui.md`
- Architecture: `../architecture/tables-reports-exports-and-print.md`
- Operations: `../operations/quality-gates-and-git.md`
- Repository rules: `../../AGENTS.md`

## Implementation contract

- Audit every existing frontend page, layout, component composition, navigation entry, table, filter surface, form surface, card, status panel, empty state, unavailable state, and action area. Auth, application, and Admin surfaces are all in scope; Admin screens are only the first migration batch because they are the current dominant frontend surface.
- Maintain a frontend audit matrix for this phase. Each audited view records route name, Vue page, layout, controller or data provider, sidebar entry, breadcrumb, backend permission, module gate, active-team behavior, demo/e2e seeder visibility, shared primitives used, exceptions, manual review URL, and review account.
- Rebuild frontend pages around shared primitives instead of page-local card, header, filter, table, form, badge, tooltip, formatter, visual-state, and action patterns.
- Keep the accepted Atlas visual direction, but make it systematic and reusable.
- Admin and regular application shells may differ, but shared UI primitives must be common unless a component is genuinely coupled to a shell, route family, or permission boundary.
- Do not name shared primitives after `Admin` or `App` unless that boundary is real. Cards, tables, forms, filters, dialogs, badges, tooltips, formatters, and visual states belong to the shared Atlas frontend layer.
- Every operational card has an icon unless a documented shared primitive explicitly defines a different pattern.
- Main operational cards use larger colored icon treatment.
- Secondary cards such as filters, compact status sections, and helper panels use smaller neutral icon treatment.
- Card headers use one shared header structure matching the current Admin dashboard card header band: background, bottom border, spacing, typography, icon placement, subtitle behavior, actions, light theme, and dark theme.
- Do not remove established icons, status treatments, header backgrounds, or accepted visual affordances while changing layout unless the replacement is explicitly documented and applied consistently.
- Data that can be represented as a normal table uses the shared `DataTable` foundation.
- Custom table-like layouts are allowed only when a documented interaction or visualization requirement cannot be represented by `DataTable`.
- Forms, filters, inputs, selects, checkboxes, switches, dialogs, confirmations, alerts, toasts, tooltips, badges, empty states, loading states, unavailable states, and permission-denied states use shared primitives.
- Date, time, number, boolean, status, permission, module, team, money, file-size, duration, and route/action labels use shared formatters or shared display primitives instead of page-local string formatting.
- Before creating or changing any frontend view, implementation must inspect similar existing views and shared components, then follow or extend the closest accepted pattern.
- New shared primitives must be named clearly, documented, typed, accessible, theme-complete, and designed for future module screens.
- Pages compose shared primitives and module-specific data only; they must not contain repeated design-system logic.
- The application template has fixed navigation responsibilities. The sidebar is for modules and other primary work areas, including Admin operational areas. The top bar or a documented module subnavigation slot is for secondary views, tabs, subsections, and actions inside the currently selected module or area. Do not render module subnavigation as a standalone page card.
- A module, application area, or Admin area with multiple sibling views must expose those sibling views through the shared shell/top navigation pattern, not through page-local tab cards. The active subsection must be visible in the shell, share the same route/permission/module-gate model, and preserve breadcrumbs.
- Do not add sidebar entries for every subsection of a module. Add one sidebar entry for the owning module or primary area, then place subsection links such as runs/imports/definitions/schedules in the module subnavigation.
- The Admin sidebar, breadcrumbs, route availability, permission gates, module gates, and demo/e2e visibility seeders must be verified together for every Admin route.
- Demo reset must leave demo users able to see and access every intended Auth, application, and Admin route for their enabled modules, permissions, and active-team context.
- Frontend review output must list every created or changed view and the account/route needed to inspect it.
- `AGENTS.md` and canonical frontend architecture documentation must contain enforceable rules for future frontend work so the same drift is not reintroduced.

## Frontend audit matrix

The implementation must fill this matrix before marking the inventory and replacement tasks complete.

Initial inventory repair notes:

- `admin.users.impersonate` now has an Atlas breadcrumb because the view is rendered through `AdminLayout` and participates in Admin navigation context.
- `admin.integrations.index` now has an Atlas breadcrumb because it is a first-class Admin sidebar route.
- `E2eVisibilitySeeder` now activates `search` in addition to integrations, managed processes, and imports so the Admin search sidebar route can be verified after e2e seeding.
- `admin.pulse.view` and `admin.telescope.view` remain external diagnostic tools, not Atlas Vue pages; they are tracked for sidebar visibility but do not need Atlas page primitives.

| Area | Route name | Vue page | Layout | Controller/data provider | Sidebar | Breadcrumb | Permission | Module gate | Active team | Demo/e2e visibility | Shared primitives | Exceptions | Manual review |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Auth | `login` | `Auth/Login` | `AuthLayout` | `routes/web/auth.php` | No | `auth.php` | Guest | `identity` | No | Demo/e2e users use `admin@example.test` / `password` | `AuthLayout`, `SurfaceCard`, `AtlasForm`, `FormInput`, `FormCheckbox`, `FormButton`, `NoticeBanner` | Public auth surface | `/login` |
| Auth | `password.confirm` | `Auth/ConfirmPassword` | `AuthLayout` | `ConfirmPasswordController@show` | No | `auth.php` | Authenticated | `identity` | Session-dependent | Required for Admin mode and high-risk flows | `AuthLayout`, `SurfaceCard`, `AtlasForm`, `FormInput`, `FormButton` | Security checkpoint view | `/user/confirm-password` |
| Auth | `password.reset` | `Auth/ResetPassword` | `AuthLayout` | `routes/web/auth.php` | No | `auth.php` | Guest | `identity` | No | Token-dependent manual review | `AuthLayout`, `SurfaceCard`, `AtlasForm`, `FormInput`, `FormButton` | Token route | `/reset-password/{token}` |
| Application | `team.select` | `Teams/Select` | `AppLayout` | `ActiveTeamController@select` | No | `application.php` | Authenticated | `teams` | Chooses active team | Demo admin redirects to dashboard because it has exactly one team; multi-team users render this view | `PageStack`, `SurfaceCard`, `FormButton` | Active-team setup surface | `/team/select` |
| Application | `dashboard` | `Dashboard` | `AppLayout` | `routes/web/application.php` | App sidebar | `application.php` | `dashboard` | `authorization` | Yes | Demo/e2e admin and workspace user | `AppLayout` only | Empty application shell host until business dashboard widgets exist | `/` |
| Application | `notifications.index` | `Notifications/Index` | `AppLayout` | `NotificationCenterController` | Top-bar notification entry | `application.php` | `notifications.index` | `notifications` | Yes | Demo/e2e admin only pending route coverage | `DataTable` | Application table, not Admin | `/notifications` |
| Admin dashboard | `admin.system-status` | `Admin/SystemStatus` | `AdminLayout` | `AdminSystemStatusController` | Workspace/Admin dashboard | Yes | `admin.system-status` | `authorization` | Yes | E2E visibility covers Admin entry and dashboard | `ComposableViewHost`, `TextBadge` | Host view with partial status endpoints | `/admin` |
| Admin dashboard partial | `admin.system-status.release` | None, data endpoint | N/A | `AdminSystemStatusController@release` | No direct sidebar | No dedicated crumb | Same route permission | `health` | Yes | E2E visibility expects element availability | N/A | JSON/fragment endpoint consumed by `ComposableViewHost` | `/admin/system-status/release` |
| Admin dashboard partial | `admin.system-status.readiness` | None, data endpoint | N/A | `AdminSystemStatusController@readiness` | No direct sidebar | No dedicated crumb | Same route permission | `health` | Yes | E2E visibility expects element availability | N/A | JSON/fragment endpoint consumed by `ComposableViewHost` | `/admin/system-status/readiness` |
| Admin dashboard partial | `admin.system-status.modules` | None, data endpoint | N/A | `AdminSystemStatusController@modules` | No direct sidebar | No dedicated crumb | Same route permission | `authorization` | Yes | E2E visibility expects element availability | N/A | JSON/fragment endpoint consumed by `ComposableViewHost` | `/admin/system-status/modules` |
| Identity access | `admin.users.index` | `Admin/Users/Index` | `AdminLayout` | `UserAdministrationController` | Identity access / Users | Yes | `admin.users.index` | `users` | Yes | Demo/e2e admin; impersonation e2e starts here | `DataTable` | Primary Admin table | `/admin/users` |
| Identity access | `admin.users.create` | `Admin/Users/Create` | `AdminLayout` | `CreateUserAccountController` | Via Users page | Yes | `admin.users.create` | `users` | Yes | Demo admin manual review | `PageStack`, `SurfaceCard`, `AtlasForm`, shared form primitives, `UiState` | Create form | `/admin/users/create` |
| Identity access | `admin.users.edit` | `Admin/Users/Edit` | `AdminLayout` | `EditUserAccountController` | Via Users page | Yes | `admin.users.edit` | `users` | Yes | Demo/e2e admin with seeded users | `PageStack`, `SurfaceCard`, `AtlasForm`, `CheckboxList`, shared form primitives, `UiState` | Complex team/authorization surface | `/admin/users/{user}/edit` |
| Identity access | `admin.users.impersonate` | `Admin/Impersonation/Start` | `AdminLayout` | `ImpersonationController@create` | Via Users page | Yes | `admin.users.impersonate` | `users` | Yes | Impersonation e2e covers path | `PageStack`, `SurfaceCard`, `NoticeBanner`, `AtlasForm`, shared form primitives | High-risk flow | `/admin/users/{user}/impersonate` |
| Identity access | `admin.authorization.roles.index` | `Admin/Authorization/Roles` | `AdminLayout` | `RoleAdministrationController` | Identity access / Roles | Yes | `admin.authorization.roles.index` | `authorization` | Yes | Demo admin manual review | `DataTable` | Role table | `/admin/authorization/roles` |
| Identity access | `admin.authorization.roles.create` | `Admin/Authorization/Roles/Create` | `AdminLayout` | `CreateRoleController` | Via Roles page | Yes | `admin.authorization.roles.create` | `authorization` | Yes | Demo admin manual review | `SurfaceCard`, `AtlasForm`, `CheckboxList`, shared form primitives | Create form | `/admin/authorization/roles/create` |
| Identity access | `admin.authorization.roles.edit` | `Admin/Authorization/Roles/Edit` | `AdminLayout` | `EditRoleController` | Via Roles page | Yes | `admin.authorization.roles.edit` | `authorization` | Yes | Demo admin manual review | `SurfaceCard`, `AtlasForm`, `CheckboxList`, shared form primitives, `UiState` | High-risk update on submit | `/admin/authorization/roles/{role}/edit` |
| Identity access | `admin.authorization.packages.index` | `Admin/Authorization/Packages` | `AdminLayout` | `OnboardingPackageAdministrationController` | Identity access / Presets | Yes | `admin.authorization.packages.index` | `authorization` | Yes | Demo admin manual review | `DataTable` | Package table | `/admin/authorization/packages` |
| Identity access | `admin.authorization.packages.create` | `Admin/Authorization/Packages/Create` | `AdminLayout` | `CreateOnboardingPackageController` | Via Presets page | Yes | `admin.authorization.packages.create` | `authorization` | Yes | Demo admin manual review | `SurfaceCard`, `AtlasForm`, `CheckboxList`, shared form primitives | Create form | `/admin/authorization/packages/create` |
| Identity access | `admin.authorization.packages.edit` | `Admin/Authorization/Packages/Edit` | `AdminLayout` | `EditOnboardingPackageController` | Via Presets page | Yes | `admin.authorization.packages.edit` | `authorization` | Yes | Demo admin manual review | `SurfaceCard`, `AtlasForm`, `CheckboxList`, shared form primitives | Edit form | `/admin/authorization/packages/{package}/edit` |
| Identity access | `admin.authorization.permissions.index` | `Admin/Authorization/Permissions` | `AdminLayout` | `PermissionAdministrationController` | Identity access / Permissions | Yes | `admin.authorization.permissions.index` | `authorization` | Yes | Demo admin manual review | `DataTable`, status primitives | Permission catalog surface | `/admin/authorization/permissions` |
| Organization | `admin.teams.index` | `Admin/Teams/Index` | `AdminLayout` | `TeamAdministrationController@index` | Organization / Teams | Yes | `admin.teams.index` | `teams` | Yes | Demo/e2e admin has active team | `DataTable` | Team table | `/admin/teams` |
| Organization | `admin.teams.create` | `Admin/Teams/Create` | `AdminLayout` | `TeamAdministrationController@create` | Via Teams page | Yes | `admin.teams.create` | `teams` | Yes | Demo admin manual review | `SurfaceCard`, `AtlasForm`, shared form primitives, `UiState` | Create form | `/admin/teams/create` |
| Organization | `admin.teams.edit` | `Admin/Teams/Edit` | `AdminLayout` | `TeamAdministrationController@edit` | Via Teams page | Yes | `admin.teams.edit` | `teams` | Yes | Demo admin manual review | `SurfaceCard`, `AtlasForm`, shared form primitives, `UiState` | Module overrides in team context | `/admin/teams/{team}/edit` |
| Organization | `admin.managers.index` | `Admin/Managers/Index` | `AdminLayout` | `ManagerHierarchyAdministrationController@index` | Organization / Managers | Yes | `admin.managers.index` | `teams` | Yes | Demo admin manual review | `PageStack`, `SurfaceCard`, `AtlasForm`, `DataTable`, shared form primitives, `UiState`, shared timestamp formatter | Hierarchy workflow | `/admin/managers` |
| Oversight | `admin.audit.index` | `Admin/Audit/Index` | `AdminLayout` | `AuditBrowserController@index` | Oversight / Audit | Yes | `admin.audit.index` | `audit` | Yes | Audit saved-view e2e covers table | `DataTable`, saved views | Primary saved-view table | `/admin/audit` |
| Oversight | `admin.audit.security-history.index` | `Admin/Audit/SecurityHistory` | `AdminLayout` | `SecurityHistoryController` | Oversight / Security history | Yes | `admin.audit.security-history.index` | `audit` | Yes | Demo admin manual review | `PageStack`, `FilterPanel`, `DataTable` | Security audit table | `/admin/audit/security-history` |
| Oversight | `admin.audit.impersonation.show` | `Admin/Audit/ImpersonationSession` | `AdminLayout` | `AuditBrowserController@impersonationSession` | Via Audit records | Yes | `admin.audit.impersonation.show` | `audit` | Yes | Demo/e2e after impersonation flow | `PageStack`, `SurfaceCard`, shared status display | Detail view | `/admin/audit/impersonation/{session}` |
| Oversight | `admin.logs.index` | `Admin/Logs/Index` | `AdminLayout` | `AdminApplicationLogController` | Oversight / Logs | Yes | `admin.logs.index` | `authorization` | Yes | Demo admin manual review | `PageStack`, `SurfaceCard`, `TextBadge`, `CodeViewer`, `UiState` | Expandable operational log records | `/admin/logs` |
| Oversight | `admin.queues.index` | `Admin/Queues/Index` | `AdminLayout` | `AdminFailedJobController@index` | Oversight / Queues | Yes | `admin.queues.index` | `authorization` | Yes | Demo admin manual review | `PageStack`, `SurfaceCard`, `FilterPanel`, `TextBadge`, `CodeViewer`, `UiState`, shared form primitives | Expandable retry action surface | `/admin/queues` |
| Oversight | `admin.files.index` | `Admin/Files/Index` | `AdminLayout` | `AdminFilesController@index` | Oversight / Files | Yes | `admin.files.index` | `files` | Yes | Demo admin manual review | `MetricGrid`, `FilterPanel`, `DataTable`, `StatusBadge`, shared `file-size` formatter and row confirmation | Rescan action surface | `/admin/files` |
| Oversight | `admin.integrations.index` | `Admin/Integrations/Index` | `AdminLayout` | `AdminIntegrationsController@index` | Oversight / Integrations | Yes | `admin.integrations.index` | `integrations` | Yes | E2E activates `integrations`; manual review pending | `MetricGrid`, `SurfaceCard`, `DataTable`, `StatusBadge`, `SeverityBadge` | Optional module surface | `/admin/integrations` |
| Oversight | `admin.search.index` | `Admin/Search/Index` | `AdminLayout` | `AdminSearchController@index` | Oversight / Search | Yes | `admin.search.index` | `search` | Yes | E2E activates `search`; manual review pending | `PageStack`, `SurfaceCard`, `AtlasForm`, `FormButton`, `StatusBadge` | Optional module surface | `/admin/search` |
| Oversight | `admin.managed-processes.index` | `Admin/ManagedProcesses/Runs` | `AdminLayout` | `AdminManagedProcessesController@index` | Oversight / Managed processes | Yes | `admin.managed-processes.index` | `managed_processes` | Yes | Managed-processes e2e covers runs | `DataTable`, shell subnavigation | Primary operational table | `/admin/managed-processes` |
| Oversight | `admin.managed-processes.imports.index` | `Admin/ManagedProcesses/Imports` | `AdminLayout` | `AdminManagedProcessesController@imports` | Managed process subview | Yes | `admin.managed-processes.imports.index` | `managed_processes` | Yes | Managed-processes e2e covers imports | `DataTable`, shell subnavigation | Import operational table | `/admin/managed-processes/imports` |
| Oversight | `admin.managed-processes.definitions.index` | `Admin/ManagedProcesses/Definitions` | `AdminLayout` | `AdminManagedProcessesController@definitions` | Managed process subview | Yes | `admin.managed-processes.definitions.index` | `managed_processes` | Yes | Managed-processes e2e covers definitions | `DataTable`, shell subnavigation | Run action surface | `/admin/managed-processes/definitions` |
| Oversight | `admin.managed-processes.schedules.index` | `Admin/ManagedProcesses/Schedules` | `AdminLayout` | `AdminManagedProcessesController@schedules` | Managed process subview | Yes | `admin.managed-processes.schedules.index` | `managed_processes` | Yes | Demo admin manual review | `PageStack`, `SurfaceCard`, `SectionHeader`, `FilterPanel`, `DataTable`, shell subnavigation | Schedule action surface | `/admin/managed-processes/schedules` |
| Oversight | `admin.managed-processes.show` | `Admin/ManagedProcesses/Show` | `AdminLayout` | `AdminManagedProcessesController@show` | Via process tables | Yes | `admin.managed-processes.show` | `managed_processes` | Yes | Demo/e2e seeded run available | `PageStack`, `SurfaceCard`, `FormButton`, `DataTable`, `CodeViewer`, status primitives | Detail and logs view | `/admin/managed-processes/{run}` |
| Oversight | `admin.imports.index` | Redirect to managed imports | N/A | `AdminImportsController@index` | No direct sidebar | Yes | `admin.imports.index` | `imports` | Yes | E2E activates `imports` | N/A | Redirect route | `/admin/imports` |
| Oversight | `admin.rate-limits.index` | `Admin/RateLimits/Index` | `AdminLayout` | `RateLimitAdministrationController` | Oversight / Rate limits | Yes | `admin.rate-limits.index` | `identity` | Yes | Demo admin manual review | `SurfaceCard`, `DataTable`, `DialogPanel`, shared form primitives | Reset action surface | `/admin/rate-limits` |
| Oversight | `admin.modules.index` | `Admin/Modules/Index` | `AdminLayout` | `ModuleActivationController@index` | Oversight / Modules | Yes | `admin.modules.index` | `authorization` | Yes | Demo admin manual review | `DataTable`, status primitives | Module activation overview | `/admin/modules` |
| Oversight | `admin.modules.show` | `Admin/Modules/Show` | `AdminLayout` | `ModuleActivationController@show` | Via Modules page | Yes | `admin.modules.show` | `authorization` | Yes | Demo admin manual review | `PageStack`, `SurfaceCard`, shared form primitives, status primitives | Activation and schedules detail | `/admin/modules/{module}` |
| Diagnostics | `admin.pulse.view` | External Pulse UI | External | Pulse route package | Oversight / Pulse | No Atlas breadcrumb | `admin.pulse.view` | `authorization` | Yes | Sidebar visibility only | N/A | External tool, not Atlas Vue | `/admin/pulse` |
| Diagnostics | `admin.telescope.view` | External Telescope UI | External | Telescope route package | Oversight / Telescope | No Atlas breadcrumb | `admin.telescope.view` | `authorization` | Yes | Local/development only | N/A | External tool, not Atlas Vue | `/telescope` |

## Shared primitive inventory

Current accepted shared primitives discovered during the opening audit:

- Layout and navigation: `AppLayout`, `AdminLayout`, `AuthLayout`, `Sidebar`, `SidebarNavNode`, `MobileNavigation`, `TopBar`.
- Page actions and record actions: `ActionLink`, `IconButton`, `RecordActions`, `FormActions`.
- Cards, metrics, and states: `CardHeader`, `MetricGrid`, `UiState`, `IconTile`.
- Tables and table support: `DataTable`, `StatusBadge`, `SeverityBadge`, `TruncatedText`, `OverflowTooltip`.
- Forms and filters: `AtlasForm`, `FilterPanel`, `FormInput`, `FormTextarea`, `FormSelect`, `FormAutocomplete`, `EntitySearchInput`, `FormCheckbox`, `FormRadioGroup`, `FormDateInput`, `FormDateTimeInput`, `FormMoneyInput`, `FormFileUpload`, `FormFieldError`, `FormButton`, `CheckboxList`.
- Overlays and feedback: `ModalHost`, `DialogPanel`, `Tooltip`, `Popover`, `ToastViewport`, `NoticeBanner`, `TextBadge`.
- Technical display: `CodeViewer`.
- Formatting: `resources/js/Utils/formatters.ts`.

Opening consolidation backlog and migration notes:

- The managed-process Admin area used `ManagedProcessTabs` as a standalone card inside `Admin/ManagedProcesses/Runs`, `Admin/ManagedProcesses/Imports`, `Admin/ManagedProcesses/Definitions`, and `Admin/ManagedProcesses/Schedules`. This has been replaced by `ShellSubnavigation` through `AdminLayout`/`TopBar`. The sidebar keeps one Managed processes entry, while Runs, Imports, Definitions, and Schedules are secondary navigation for that area.
- The imports Admin entry currently redirects into managed-process imports. Phase 22a must decide and document whether Imports is an independent module-level sidebar entry or a secondary managed-process subsection. The final structure must not duplicate the same workflow in both sidebar and page-local tabs.
- Local table markup in `Admin/Audit/SecurityHistory`, `Admin/Files/Index`, `Admin/Integrations/Index`, and `Admin/ManagedProcesses/Show` has been moved to `DataTable`.
- `DataTableAction.confirm` now uses the shared modal flow for row-level action confirmation, so operational row actions do not need page-local dialogs or native browser confirmations.
- `Admin/Managers/Index` relationship history has moved to `DataTable`. Active relationships now render as operational records with inline end forms instead of a local table, because ending a relationship is a workflow and not plain tabular browsing.
- Page-local card/section shells in the first Admin migration batch have been moved behind `SurfaceCard` where they represent normal page surfaces.
- `Admin/Search/Index` has moved from a native `<form>` to `AtlasForm` plus `SurfaceCard`.
- `DataTable` now supports `file-size` formatting through `formatFileSize`.
- `Admin/Managers/Index` now uses shared timestamp formatting for relationship dates.
- Native form controls were not found directly in pages during the opening audit, which means the existing form-control guardrail is mostly holding.

New shared primitives introduced during Phase 22a:

- `PageStack` for page width and vertical rhythm.
- `SurfaceCard` for shared card shells.
- `SectionHeader` for unframed section headings.
- `ShellSubnavigation` for top/shell module subsection navigation.
- `NoticeBanner` for inline warnings, status notes, and non-toast feedback blocks.
- `TextBadge` for compact textual statuses with optional icons.
- `IconTile` for the approved standalone icon treatment outside card headers.
- `DialogPanel` for local informational dialogs that need an accessible overlay, Escape handling, focus restore, and shared icon treatment without using the global confirmation modal queue.
- Shared UI guardrails now enforce card-header discipline: titled page-level `SurfaceCard` instances must provide an icon or an explicit exception, titled `SurfaceCard` headers must render with the dashboard-style header band, anonymous page-level `SurfaceCard` wrappers must be accessibly labelled, and `SectionHeader` requires an icon.

Naming correction:

- The shared card primitive was renamed from `AdminCard` to `SurfaceCard` because cards are not Admin-only. The same principle applies to future shared UI primitives: use domain-neutral names for shared building blocks and reserve `Admin*` / `App*` names for shell-specific components.

Completed migration batch:

- `AdminLayout`, `AppLayout`, and `TopBar` now expose shell subnavigation for secondary module views.
- Managed-process Runs, Imports, Definitions, and Schedules now use shell subnavigation instead of page-local tab cards.
- Managed-process Schedules now composes `SurfaceCard`, `SectionHeader`, `FilterPanel`, and `DataTable` as sibling primitives.
- Managed-process Show now uses `PageStack`, `SurfaceCard`, `FormButton`, and `DataTable` for import row errors.
- Search Admin now uses `PageStack`, `SurfaceCard`, `AtlasForm`, and shared form controls.
- Integrations Admin now uses `MetricGrid`, `SurfaceCard`, `DataTable`, and shared row actions.
- Files Admin now uses `MetricGrid`, `FilterPanel`, `DataTable`, and the shared `file-size` formatter.
- Security history now uses `PageStack`, `FilterPanel`, and `DataTable`.
- Managers Admin now uses `PageStack`, `SurfaceCard`, shared timestamp formatting, `DataTable` for relationship history, and shared empty states.
- Authorization role and preset create/edit screens now use `SurfaceCard`, shared form actions, and shared checklist primitives instead of page-local card shells.
- Team create/edit screens now use `SurfaceCard` and shared form/action primitives for organization management surfaces.
- User create/edit screens now use `SurfaceCard` and shared form/action primitives for identity management surfaces.
- Impersonation start and impersonation audit session screens now use `SurfaceCard` and `PageStack` for normal page surfaces.
- Rate limits now uses `SurfaceCard` for the reset panel and `DialogPanel` for reset instructions.
- Module activation detail now uses `SurfaceCard` and `PageStack` for global, team, state, history, and schedule surfaces.
- Logs and queues now use `PageStack` plus `SurfaceCard` for expandable operational records while preserving their current expand/collapse interaction.
- The application team-selection surface now uses `PageStack` and `SurfaceCard`, confirming that the new primitives are shared across App and Admin shells.
- The shared `AuthLayout` form surface now uses `SurfaceCard`, so login, password confirmation, and password reset screens share the same card contract as the rest of the frontend.
- The global error page now uses `IconTile`, `FormButton`, and `ActionLink` instead of page-local action and icon styling.
- Admin system status now uses `TextBadge` for its availability signal instead of a page-local status badge.
- `tests/e2e/admin-visibility.spec.ts` now covers Auth rendering, the application notifications table, Admin route visibility, and the sidebar-versus-shell-subnavigation contract for managed-process sections after e2e seeding.

Verification notes:

- Targeted Playwright coverage for `tests/e2e/admin-visibility.spec.ts` passes in Chromium and Firefox after e2e seeding.
- Frontend theme snapshots were regenerated for the rebuilt Auth, application, and Admin shells in Chromium and Firefox.
- `tests/e2e/frontend-surfaces.spec.ts` sweeps the current static Auth, application, and Admin frontend surfaces through the rebuilt shared shells in light and dark themes in Chromium and Firefox.
- `tests/e2e/frontend-theme.spec.ts` explicitly sets light/dark themes, waits for the fullscreen transition loader to disappear, and verifies the rebuilt shell snapshots against the current built assets.
- `playwright.config.ts` now applies an e2e-only high `ATLAS_RATE_LIMIT_AUTH_LOGIN_MAX_ATTEMPTS` value because the full browser suite performs many real login attempts. This is not a production rate-limit change.
- `tests/e2e/audit-saved-views.spec.ts` now covers the browser-critical saved-view contract: save, select, update, and copy preserve audit filters. Default/delete behavior should remain covered outside this oversized browser workflow.
- Full `pnpm test:e2e` passes after the rebuild with 22/22 tests passing in Chromium and Firefox after `pnpm build`.
- `composer demo:reset` passes on the local development database and recreates the documented `admin@example.test` / `password` demo account.
- `Tests\Feature\Foundation\DemoResetTest::test_development_demo_admin_can_access_current_core_frontend_surfaces` verifies the current Auth, application, and core Admin Vue surfaces after the foundation demo seeder, including active-team fallback and Admin mode session state.

## Final frontend review routes

Review baseline:

- Demo reset: run `composer demo:reset`, then sign in as `admin@example.test` / `password`.
- Core review scope: Auth, application shell, Admin dashboard, identity access, organization, oversight, files, logs, queues, rate limits, and module activation routes are expected to work with the minimal development demo account.
- Optional-module browser scope: integrations, search, managed processes, and imports are verified through `E2eVisibilitySeeder`, because the development demo seeder intentionally does not create optional module activation states or operational sample records.
- External diagnostics: Pulse and Telescope remain external package UIs; Phase 22a tracks their sidebar visibility only, not Atlas Vue primitive composition.

Changed or newly standardized shared frontend primitives:

- `PageStack`, `SurfaceCard`, `SectionHeader`, `ShellSubnavigation`, `NoticeBanner`, `TextBadge`, `IconTile`, and `DialogPanel`.
- Existing shared primitives extended during this phase: `CardHeader`, `DataTable`, `FilterPanel`, `UiState`, `MetricGrid`, `AuthLayout`, `AppLayout`, `AdminLayout`, and shared frontend formatters.

Changed Auth and application routes:

- `/login`
- `/user/confirm-password`
- `/reset-password/{token}`
- `/`
- `/notifications`
- `/team/select`
- global error page rendered by Inertia error handling

Changed core Admin routes:

- `/admin`
- `/admin/users`
- `/admin/users/create`
- `/admin/users/{user}/edit`
- `/admin/users/{user}/impersonate`
- `/admin/teams`
- `/admin/teams/create`
- `/admin/teams/{team}/edit`
- `/admin/managers`
- `/admin/authorization/roles`
- `/admin/authorization/roles/create`
- `/admin/authorization/roles/{role}/edit`
- `/admin/authorization/packages`
- `/admin/authorization/packages/create`
- `/admin/authorization/packages/{package}/edit`
- `/admin/authorization/permissions`
- `/admin/audit`
- `/admin/audit/security-history`
- `/admin/audit/impersonation/{session}`
- `/admin/rate-limits`
- `/admin/logs`
- `/admin/queues`
- `/admin/files`
- `/admin/modules`
- `/admin/modules/{module}`

Changed optional-module Admin routes verified with e2e fixtures:

- `/admin/integrations`
- `/admin/search`
- `/admin/managed-processes`
- `/admin/managed-processes/imports`
- `/admin/managed-processes/definitions`
- `/admin/managed-processes/schedules`
- `/admin/managed-processes/{run}`
- `/admin/imports`

## Tasks

- [x] Inventory all current frontend routes and pages, including Admin dashboard, system status, authorization, teams, sessions, module activation, settings/localization, health, notifications, audit, files, integrations, imports, managed processes, search, diagnostics screens, authenticated application shell surfaces, and authentication/profile-adjacent screens currently present.
- [x] Fill the frontend audit matrix with route, page, layout, navigation, breadcrumb, permission, module gate, seeder visibility, primitive usage, exceptions, and manual review coverage for every current frontend route.
- [x] Inventory shared frontend primitives and identify duplicate or page-local patterns that must be removed or consolidated.
- [x] Define the canonical card, card header, section header, filter panel, action bar, status panel, metric tile, table wrapper, empty state, unavailable state, and permission-denied state primitives.
- [x] Define the canonical shell navigation contract for sidebar module links and top/module subnavigation links.
- [x] Replace page-local module tab cards, starting with managed-process runs/imports/definitions/schedules, with the canonical top/module subnavigation pattern.
- [x] Implement shared card/header primitives with required icon variants: large colored main icons and small neutral secondary icons.
- [x] Replace page-local card and header implementations with the shared primitives.
- [x] Replace table-like frontend layouts with `DataTable` where the data is tabular.
- [x] Replace page-local filters, forms, dialogs, tooltips, badges, and formatting with shared primitives and formatters.
- [x] Verify every current frontend page in light and dark themes for spacing, header consistency, icon presence, focus states, loading states, empty states, unavailable states, and responsive behavior.
- [x] Verify shell/sidebar links, breadcrumbs, route availability, permissions, module gates, and active-team behavior for Auth, application, and Admin surfaces after `composer demo:reset`.
- [x] Add or update frontend tests for shared frontend primitives.
- [x] Add shared row-action confirmation support to `DataTable`.
- [x] Add or update feature/e2e coverage that catches missing shell/sidebar links and inaccessible Auth, application, or Admin routes after demo/e2e seeding.
- [x] Update `AGENTS.md` with mandatory frontend reuse, card/header, table, formatter, and pre-implementation inspection rules.
- [x] Update `docs/architecture/frontend-ui.md` with the canonical shared frontend composition contract for Auth, App, and Admin surfaces.
- [x] Update table/report documentation if shared table usage rules need clarification.
- [x] Record the final list of frontend views changed and the manual review routes in this phase file.
- [x] Commit frontend rebuild.

## Completion criteria

- [x] Current frontend is visually coherent across all existing pages.
- [x] Sidebar entries represent modules and primary work areas, including Admin operational areas; top/module subnavigation represents secondary views inside the selected area.
- [x] Managed-process runs, imports, definitions, and schedules no longer use a standalone page-card tab menu.
- [x] Pages use shared primitives for repeated UI structure instead of page-local recreations.
- [x] Every card/header follows the documented icon, background, border, spacing, typography, and theme contract.
- [x] Table-like frontend data uses `DataTable` unless a documented exception exists.
- [x] Shared formatters and display primitives are used for common values.
- [x] Demo users can see and access intended Auth, application, and Admin routes after `composer demo:reset`.
- [x] Relevant backend feature tests, frontend tests, build, lint, typecheck, and targeted e2e/manual review checks pass.
- [x] Future frontend rules are documented in `AGENTS.md` and canonical architecture docs.
