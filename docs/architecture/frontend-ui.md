# Frontend and shared UI architecture

Canonical current rules for Atlas frontend UI usage, themes, layout, routing, frontend structure, accessibility, forms, modals, confirmations, alerts, and toasts.

## Frontend and UI

Frontend views are product surfaces, not thin delivery wrappers for backend features. A change is not complete merely because routes, props, permissions, tables, and tests exist; the rendered workflow must be understandable, actionable, localized, accessible, and reviewable by the target user.

Do not patch a structurally poor view with more cards, explanatory text, warnings, or page-local styling. If a view needs long copy to explain why it shows partial data, where the real workflow lives, or what an operator should infer, redesign the view contract with proper ownership, navigation, filters, pagination, drill-down, states, and actions.

When an existing view is visibly incoherent, noisy, mixed-language, duplicated, or misleading, do not use it as the design baseline. Inspect it only to recover route contracts, props, permissions, data ownership, actions, edge cases, and regression risks, then rebuild the view around accepted shared primitives and a clear workflow.

### UI Implementation Hierarchy

When implementing UI:

1. reuse an existing shared Atlas component;
2. extend the shared Atlas UI layer when a recurring primitive is missing;
3. use Tailwind utilities for one-off composition;
4. write custom CSS only as a last resort.

Keep custom CSS minimal, ideally zero.

Reuse and extend existing shared components before creating new ones.

### Agent-facing implementation protocol

Frontend work must be easy to continue safely by an agent that only knows the repository contracts. Before creating or materially changing a view, record or verify the view contract:

- route name;
- Vue page;
- layout;
- canonical page title and browser title;
- controller or data provider;
- sidebar entry;
- breadcrumb;
- backend permission;
- module gate;
- active-team behavior;
- demo and e2e seeder visibility;
- shared primitives and formatters used;
- manual review URL and account;
- the user's primary task;
- secondary actions;
- data ownership;
- loading, empty, error, offline, no-results, and permission-denied states;
- localization source;
- expected dashboard or notification behavior;
- exact review data needed to see the workflow honestly.

The view contract prevents a visual rewrite from accidentally disconnecting navigation, authorization, module availability, breadcrumbs, demo visibility, or tests.

Implementation order:

1. inspect similar accepted views;
2. inspect shared primitives, composables, and formatters;
3. extend the shared layer when a recurring pattern is missing;
4. compose the page from shared primitives and module-specific data;
5. verify route visibility, authorization, module gates, breadcrumbs, light theme, dark theme, responsive layout, and keyboard behavior.

Pages must not contain reusable design-system decisions. Pages choose data, labels, route actions, and module-specific composition; shared components, composables, and formatters own visual structure, control styling, common states, formatting, and repeated interaction patterns.

Advanced controls are shared form primitives before they are feature-specific UI. Color pickers, image croppers, uploaders, rich text editors, date/time pickers, tag selectors, autocomplete inputs, and similar controls belong under `resources/js/Components/Form/` with generic names and configuration props. A feature-specific component may wrap them only when it composes the generic controls for one workflow and contains no reusable design-system behavior.

Reusable controls must be named after the interaction or data type, not the first business screen that needed them. Use names such as `FormMoneyInput`, `FormColorPicker`, `FormImageCropper`, `FormAutocomplete`, or `FormDateTimeInput`; do not introduce names such as `DebtEuroInput`, `AvatarColorPicker`, `ProfileUpload`, or `CaseDatePicker` unless the component is only a thin workflow wrapper and delegates all reusable control behavior to a shared primitive.

Before adding a new frontend input, modal, filter builder, status labeler, table action style, report chart formatter, or repeated preview block, perform a component inventory check against `resources/js/Components`, `resources/js/Components/Form`, `resources/js/Composables`, and `resources/js/Utils`. A second copy of the same interaction means the shared layer is missing a primitive and must be extended.

Pages must not define reusable helper families such as local `allOptions`, generic `readableToken`, generic yes/no filter builders, reusable date/currency/number formatters, or common dialog action footers. Page-local helpers are acceptable only when the labels or transformations are truly owned by that one page and cannot reasonably recur.

When one operational workflow appears in multiple contexts, such as create/edit/show, user/team sides of the same relation, or Admin/Application variants, implement it as one coherent workflow component or view module with explicit modes and typed props/events. The page may own routing, data loading, permissions, and submit endpoints, but the shared workflow module owns layout, control order, labels, empty states, previews, action placement, and mode-specific rendering.

Paired workflow surfaces must be compared side by side before editing and before declaring completion. If create and edit expose the same concept, they must keep the same composition, spacing, labels, control sequence, preview placement, and action semantics unless a documented product reason explains the difference. Adding a capability to one side requires updating the shared workflow module or explicitly documenting why the other side cannot support it.

For phase-by-phase view rebuilds, owner review findings are part of the implementation contract. Before starting or accepting a later view in the same phase, review the active phase findings log and apply every accepted Phase-wide or Atlas-wide correction. Repeated defects must become shared primitives, formatters, tests, or canonical documentation before the phase closes.

Existing accepted frontend modules must be used. Rebuilds may clear bad page implementations, but they must preserve and compose accepted shared UI primitives, composables, services, formatters, table/form/dialog/toast infrastructure, layouts, theme system, localization helpers, route helpers, and testing fixtures wherever they fit accepted contracts.

For user-reviewed UI work, prepare deterministic review data that exposes representative records, edge cases, permissions, module states, operational failures, empty states, validation paths, and action paths. Do not ask the user to approve a mostly empty or artificially happy-path screen.

Temporary review-only seeders, fixtures, helper classes, routes, UI controls, and test harnesses may exist only when explicitly tracked by the active phase. They must remain available while owner review is in progress and must be removed before the phase is marked complete unless a permanent demo-data scope is explicitly accepted.

Rendered UI must be manually or browser-automated reviewed in the active locale or locales before declaring localization complete. Backend translation-key parity alone is insufficient.

### Shell and shared frontend composition

The Atlas shell owns navigation hierarchy:

- `Sidebar` is for modules, Admin operational areas, and other primary work areas.
- `TopBar` plus inline `ShellSubnavigation` is for secondary views inside the selected module or operational area; it belongs inside the top navbar, not as a second bar below it.
- Page-local tab cards must not be used for module subsections.
- Breadcrumbs remain centralized and visible independently of shell subnavigation.
- Breadcrumbs for pages with top-navbar subsection links must include the active subsection level so the hierarchy matches the visible navigation, for example `Admin / Processes / Runs` and `Admin / Processes / Schedules / Create`.

Use `AppLayout` `subnavigation` props for module subsection links. A page may define module-specific subnavigation items locally, but the rendering, active state treatment, spacing, theme behavior, and responsive overflow belong to `ShellSubnavigation`.

Shared application, user, manager, and administrator pages use the same authenticated shell: `AppLayout` with an explicit shell mode. Do not create pass-through shell wrappers such as `AdminLayout`; shell variation belongs in `AppLayout`, `Sidebar`, `MobileNavigation`, `TopBar`, and typed navigation mode configuration.

Shared surface composition uses:

- `PageStack` for the main vertical page rhythm and width constraint.
- `SurfaceCard` for bordered card surfaces, optional header actions, and the canonical card shell.
- `CardHeader` for card titles with documented icon variants.
- `SectionHeader` for unframed section headings that must not create nested cards.
- `MetricGrid` for repeated operational metric cards.
- `FilterPanel` for custom filters outside `DataTable`.
- `DataTable` for tabular data whenever the interaction fits a normal table.
- `AtlasBarChart` for accessible repository-owned bar charts.

Every Inertia Admin page rendered through `AppLayout mode="admin"` uses `PageStack` as the outer page-content wrapper. `PageStack` is fluid by default and owns only full-width page rhythm. Admin pages must not introduce narrow page variants or recreate page width with page-local `mx-auto`, `max-w-*`, or ad hoc container classes.

Do not nest `SurfaceCard` inside another `SurfaceCard`. If a subsection contains filters plus a table, use an unframed `SectionHeader`, then `FilterPanel` and `DataTable` as siblings.

### Third-Party UI Assets

Atlas uses project-owned Vue components and Tailwind CSS for its UI foundation.

Do not introduce copied third-party UI templates, paid component source, paid chart source, or proprietary design-system assets unless the project owner explicitly requests that dependency and its license is verified for Atlas' delivery model.

Do not create duplicate:

- tooltips;
- timers;
- modal systems;
- uploaders;
- validation UIs;
- alerts;
- tables;
- formatters;
- chart wrappers.

Shared chart wrappers must use repository-owned Vue/SVG composition by default, expose accessible text labels, support light and dark themes, and avoid adding chart libraries unless a concrete missing capability justifies the dependency.

Avoid giant god-components. Extract stable common cores with focused variants or adapters.

When UI values are visually truncated or line-clamped, expose the full value through the shared tooltip pattern. The underlying rendered text must remain selectable so normal browser copy flows such as selecting a table region and using copy preserve the full value without adding dedicated copy buttons.

### Light and dark themes

Light and dark themes are developed in parallel from the beginning.

No page or component is complete if it works correctly in only one theme.

Check both themes for:

- default states;
- hover;
- focus;
- disabled;
- loading;
- error;
- success;
- forms;
- tables;
- modals;
- charts;
- notifications;
- reports;
- print layouts where applicable.

Do not postpone dark-theme fixes to the end.

Key visual and E2E tests should cover both themes.

The shell persists the selected theme through typed backend user settings for authenticated users. Guest and pre-login screens may use the non-sensitive `atlas.theme` local-storage value and `atlas_theme` cookie as temporary fallbacks until the user signs in.

### Layout

Use:

- collapsible left module sidebar;
- expanded state: icon and text;
- collapsed state: icons with custom tooltips;
- persisted sidebar state in user settings;
- module visibility by active team and permissions;
- active module indication.

Top bar:

- current module navigation;
- subsections;
- breadcrumbs;
- actions;
- local search where relevant;
- avatar;
- admin entry in the user menu;
- logout.

Team switching must be visible enough to avoid accidental context mistakes after active-team context exists.

The baseline frontend shell includes:

- `AuthLayout` for login;
- `AppLayout` for authenticated application, user, manager, and administrator screens;
- collapsible desktop sidebar;
- mobile navigation drawer;
- top bar with theme, language, avatar menu, admin entry, and logout controls.

Real team switching, profile routes, notification counts, settings, active sessions, and team-scoped state clearing are implemented by the dependency-ordered roadmap phases for settings, sessions/active team, notifications, and module activation. Backend authorization primitives already exist after Phase 7 and are completed for UI visibility coverage in Phase 8.

The authenticated Inertia shell receives `auth.availableAdminRoutes` from the backend. The sidebar and top-bar Admin entry use that list only for visibility; protected Admin routes still require backend middleware authorization and password confirmation.

Operational dashboards show concise actionable state, not sidebar navigation, raw logs, raw queue/process step streams, or architecture explanations. Dashboard signals must be deduplicated and attributed to the correct owner or shown as global when ownership is not module-specific.

When adding or materially changing an Admin operational area, add or update a meaningful Admin dashboard status signal when the area exposes health, queues, failures, approvals, security events, module state, integrations, files, imports, reports, or operator action. The Admin dashboard must not duplicate sidebar navigation; the sidebar owns navigation, and the dashboard owns operational visibility.

For large Admin or operational rebuilds, work sidebar entry by sidebar entry or workflow by workflow. Complete the full workflow for one entry, including index/list, create, edit, show/detail, dialogs, filters, row/bulk actions, exports, breadcrumbs, permissions, module gates, toasts/notifications, and subviews, then pause for browser review before continuing.

### Breadcrumbs

Every application and admin page uses centralized breadcrumbs through `diglactic/laravel-breadcrumbs`.

Breadcrumbs use:

- route names;
- translation keys;
- permissions;
- team context.

Breadcrumbs are text-only and must not render icons. The first levels describe the shell context: `Atlas` for the application root, then `Panel użytkownika`, `Panel managera`, or `Panel administratora` for panel-owned views. Later levels must reuse the same user-facing names as sidebar links, subnavigation links, action links, and primary buttons. Create/detail/edit breadcrumbs use the action label that led to the view, and object-specific breadcrumbs include the public identifier or canonical route identifier when the route addresses a concrete object.

The visible page title passed to `AppLayout`, the browser title passed to Inertia `Head`, the final breadcrumb label, and the sidebar/subnavigation/action link that opens the page must use the same canonical user-facing name unless a documented product reason requires a longer explanatory title. The `AppLayout` title icon must match the icon of the active sidebar, subnavigation, action, or tab link that opened the page; missing or mismatched title icons are UI defects unless the route has no navigation entry and the exception is documented in the view contract. For example, `/user` is `Pulpit użytkownika` everywhere, not `Profil` in one place and `Panel użytkownika` in another.

Breadcrumb definitions live in small files under `routes/breadcrumbs/` and are shared with Inertia as `navigation.breadcrumbs`.

### Routes

Every route:

- has an English name;
- uses module/resource/action naming;
- uses public ULIDs;
- is defined inside module Presentation;
- uses admin prefix and name namespace for admin routes;
- uses REST naming where appropriate;
- uses explicit business verbs for custom actions;
- never uses vague names such as `do-action`, `process`, or `handle`.

Route names drive:

- permissions;
- breadcrumbs;
- menu;
- Ziggy.

Expose only required routes through Ziggy.

### Frontend structure

Use:

- `Pages`
- `Layouts`
- `Components`
- `Composables`
- `Types`
- `Services`

Create `Utils` only when the first real shared frontend utility exists. Do not keep empty placeholder directories.

Rules:

- Pages compose UI;
- no domain logic in Vue;
- strict TypeScript;
- avoid `any` where typeable;
- typed props and events;
- do not duplicate backend state unnecessarily.

State rules:

- Inertia and local component state by default;
- composables for shared UI logic;
- Pinia only for true cross-screen persistent state;
- stores do not contain business logic;
- clear team-scoped state on team switch.

### Composable Views

Atlas uses a generic composable-view system for coded host views made of independently registered elements.

Current contracts live in:

- `resources/js/Types/composable-view.ts`;
- `resources/js/Services/composableViewRegistry.ts`;
- `resources/js/Services/composableViewHostLayouts.ts`;
- `resources/js/Components/ComposableView/ComposableViewHost.vue`.

Host views have explicit technical keys, view types, accepted element keys, ordering, areas, and dimensions in code. The current host keys are:

- `app.dashboard`;
- `admin.system-status`.

Reusable host layout presets are:

- `dashboard-sidebar` for dashboards and module landing pages;
- `overview-grid` for overview and module landing pages;
- `manager-workspace` for manager workspaces;
- `operational-status` for system and operational status views.

View elements declare:

- stable technical key;
- supported host view types;
- explicit supported host keys;
- translation keys and current fallback copy;
- permission, module, and active-team requirements for later backend enforcement;
- component;
- data provider;
- cache TTL;
- realtime support;
- whether the element is optional or structural.

Users and administrators cannot reorder, resize, hide, show, or personalize view elements in the current scope.

Composable-view layout configuration is not stored in Settings.

Unavailable optional elements are removed from the coded layout without leaving broken empty slots. Unavailable structural elements remain visible with their independent state.

Each element owns its own loading, empty, error, unavailable, and permission-denied state. A failed data provider renders that element's error state and does not prevent the rest of the host view from rendering.

The active-team, permission, and module-gate requirements are metadata for coded view elements until the dependency-ordered sessions/active-team and module-activation phases connect real backend enforcement. Authorization primitives already exist after Phase 7; Phase 8 closes the first permission/module-gated visibility e2e coverage.

### Accessibility

Target WCAG 2.2 AA.

Require:

- keyboard access;
- visible focus;
- semantic controls;
- accessible labels;
- sufficient contrast;
- no color-only meaning;
- accessible modals;
- accessible tooltips;
- accessible tables;
- accessible charts where possible.

Fix inaccessible shared components rather than accepting defects.

### Icons and tooltips

Use Tabler Icons consistently.

Do not rely on icons alone for unclear actions.

Use custom tooltips and popovers.

Never use native `title` attributes.

Tooltips must support hover and focus.

Critical information must not be hover-only.

Atlas owns one shared popover primitive and one shared tooltip component under `resources/js/Components`. New hover/focus hints and floating option panels must extend those components instead of introducing native `title` attributes or local popover state machines.

### Forms

Use `novalidate`.

Backend validation is the source of truth.

Ordinary feature pages must not render native form controls directly. Native `input`, `select`, `textarea`, checkbox, radio, and switch controls belong inside shared form primitives, currently including `FormInput`, `FormSelect`, and `FormCheckbox`, so styling, focus states, validation display, and theme behavior stay consistent across the system.

Use shared form components for:

- text;
- textarea;
- select;
- multiselect;
- checkbox;
- radio;
- date;
- datetime;
- money;
- upload;
- autocomplete;
- entity search;
- field errors;
- buttons.

Current shared primitives live under `resources/js/Components/Form` and include `AtlasForm`, `FormInput`, `FormTextarea`, `FormSelect`, `FormAutocomplete`, `EntitySearchInput`, `FormCheckbox`, `FormRadioGroup`, date and datetime inputs, `FormMoneyInput`, `FormFileUpload`, `FormFieldError`, and `FormButton`.

Custom filter forms that are not owned by the shared `DataTable` wrapper use `resources/js/Components/FilterPanel.vue`. The panel keeps the heading, neutral Clear action, primary Apply action, spacing, result summary, and light/dark theme treatment consistent across custom operational screens. Do not hand-build local filter button rows when this panel fits.

Page-level action links such as Create and Back use `resources/js/Components/ActionLink.vue`, and ordinary form footers use `resources/js/Components/FormActions.vue`. This keeps primary link buttons, neutral navigation links, focus treatment, wrapping, and spacing consistent without duplicating long Tailwind class strings in pages.

Repeated operational count/status cards use `resources/js/Components/MetricGrid.vue`. Use it for compact page-level metrics before hand-building local metric card grids.

Application, Admin, and operational card titles use `SurfaceCard`, `CardHeader`, and `SectionHeader` so card headers keep one visual language for title weight, subtitle spacing, background, border, icon placement, actions, and dark-theme treatment across the system. Titled `SurfaceCard` headers must render like the current Admin dashboard cards: a distinct header band with the shared background, bottom border, `px-4 py-3` spacing, title/subtitle stack, approved icon tile, optional actions on the right, and matching dark-theme treatment. Phase 22a deliberately redesigns the shared card system around documented icon variants: larger colored icons for main operational cards and smaller neutral icons for secondary cards such as filters, compact status sections, and helper panels. Do not hand-build local header structures or one-off icon tiles in pages.

Every page-level `SurfaceCard` with a title must pass an approved icon or explicitly suppress the icon through a documented component-level exception. Anonymous `SurfaceCard` usage is allowed only for deliberate structural wrappers or repeated record rows, and those wrappers must expose an accessible label. `SectionHeader` always requires an icon because it is a visible section heading.

Do not name shared primitives after `Admin` or `App` unless the component is coupled to that shell, route family, or permission boundary. A shared card is `SurfaceCard`, not `AdminCard`; authenticated shell variation belongs in the shared `AppLayout` mode contract instead of separate pass-through layout wrappers.

Repeated role, permission, and option checklists use `resources/js/Components/CheckboxList.vue` instead of rebuilding local checkbox grids in pages. Keep one-off binary settings on `FormCheckbox`.

Technical payloads, JSON/TOML snippets, log details, code-like details, and stack traces use `resources/js/Components/CodeViewer.vue`. The viewer owns line numbering, wrapping controls, copy-to-clipboard behavior, tooltips for icon-only controls, breathing room around short snippets, and basic code/log line highlighting so pages can focus on selecting the payload and context. Do not hand-build local `<pre>` blocks or one-off stack-trace renderers in pages when this viewer fits. Do not force raw code or log bodies into `DataTable` as the primary reading experience; use tables for selectable records and `CodeViewer` for the focused code/log/payload surface.

The Admin dashboard keeps its three primary operational cards: Release, Readiness, and Modules. Module-owned operational areas contribute status signals into the Modules card instead of adding standalone dashboard cards unless a new dashboard structure is deliberately designed and documented first.

Requirements:

- common loading, disabled, success, and error states;
- prevent double submission;
- warn about unsaved changes;
- common reset behavior;
- permission-aware UI;
- map backend field errors;
- money input converts to backend minor units through one shared formatter.

`AtlasForm` is the ordinary page-level form wrapper. It sets `novalidate`, exposes `aria-busy`, and blocks duplicate submits while processing. Pages should not render native `<form>` submit handling directly.

### Modals and confirmation

Use one shared modal and confirmation system.

Never use:

- `window.confirm`;
- `window.alert`.

Requirements:

- focus trapping;
- Esc behavior;
- restore focus;
- avoid nested modals;
- exact destructive target description;
- irreversibility warning;
- typed confirmation for high-risk operations;
- affected-row count for mass actions;
- stronger flows for hard delete and anonymization.

The shared modal host supports focus trap, Escape close for confirm dialogs, focus restoration, destructive metadata, affected-row counts, irreversibility warnings, and typed confirmation text. Avoid modal-on-modal flows by routing confirmations through `useModal`.

### Alerts and toasts

Use one shared system for backend and frontend messages.

Use standardized Inertia flash/shared contracts and translation keys.

Support:

- success;
- info;
- warning;
- error;
- close action;
- configurable auto-dismiss;
- colored bottom progress bar by type;
- longer or manual dismissal for critical alerts;
- multiple queued alerts;
- accessibility.

No native alerts and no local ad hoc toast systems.

The Inertia flash contract accepts queued messages with type, translation key or message text, optional translated description key, configurable timeout, and a critical flag for manual dismissal. A single user action must produce at most one immediate request-result toast/flash. For asynchronous workflows, progress belongs in the owning detail/list view and the terminal outcome may create one durable notification; realtime progress events must not create toast stacks. Frontend events use `useToast` and render through `ToastViewport`.

### States and formatters

Use the shared `UiState` component for loading, empty, error, and no-results states.

Loading, empty, error, offline, and permission-denied states are first-class UI states. They must be part of the view contract and verified where they affect the workflow.

Bounded datasets must be represented through real controls such as server-side pagination, filters, date/range controls, counters, saved views, and deep links. Do not use prominent bounded-view notices as the primary mechanism for compensating for incomplete tables or weak operational views.

Use shared frontend formatters from `resources/js/Utils/formatters.ts` for date, time, datetime, money, number, percent, file size, status, and empty values. Money conversion uses integer minor units at component boundaries.

---

## Internationalization and Documentation

### UI languages

- Polish
- English

Polish is the default UI language.

Use translation keys, never source text as keys.

No hardcoded UI strings.

Maintain UI translation completeness for supported regular-user interface languages.

### Technical language

Use English for:

- backend technical messages;
- exceptions;
- CLI;
- code;
- documentation;
- admin panel.
