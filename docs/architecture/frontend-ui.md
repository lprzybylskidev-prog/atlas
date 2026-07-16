# Frontend and shared UI architecture

Canonical current rules for TailAdmin usage, themes, layout, routing, frontend structure, accessibility, forms, modals, confirmations, alerts, and toasts.

## Frontend and UI

### TailAdmin hierarchy

When implementing UI:

1. check TailAdmin Vue Starter / Pro first;
2. use Tailwind utilities if no suitable component exists;
3. use custom CSS only as a last resort.

Keep custom CSS minimal, ideally zero.

Reuse and extend existing shared components before creating new ones.

### TailAdmin licensing guard

Atlas starts with TailAdmin Free patterns and project-owned components only.

TailAdmin Pro source, paid charts, paid assets, or Pro-only template fragments must not be copied, reproduced one-to-one, or introduced before the project owner explicitly confirms that the appropriate company license has been purchased and verified for Atlas use.

The current TailAdmin Pro license state is **not confirmed** and is recorded in `config/atlas.php` as `atlas.ui.tailadmin.pro_license_state = not_confirmed`.

The related environment variables are:

- `ATLAS_TAILADMIN_PRO_LICENSE_STATE`;
- `ATLAS_TAILADMIN_PRO_LICENSE_CONFIRMED_AT`;
- `ATLAS_TAILADMIN_PRO_LICENSE_CONFIRMED_BY`;
- `ATLAS_TAILADMIN_PRO_REDISTRIBUTION_CONFIRMED`.

If a Pro-only need appears:

- stop implementation before introducing the asset;
- ask for one explicit confirmation that the license has been purchased;
- verify redistribution/source-transfer rights before release;
- update the recorded license state so future work does not repeat the same question;
- keep or build a non-Pro fallback when redistribution is not permitted.

The first frontend review checkpoint uses project-owned components and no TailAdmin Pro assets.

TailAdmin Vue Starter is installed as the reviewed Free TailAdmin source checkpoint documented in [TailAdmin Vue Starter](tailadmin-vue-starter.md). Atlas does not vendor the upstream source tree unless the exact asset/license status is verified for source transfer.

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

Avoid giant god-components. Extract stable common cores with focused variants or adapters.

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
- `AppLayout` for authenticated application screens;
- `AdminLayout` for authenticated administrative screens;
- collapsible desktop sidebar;
- mobile navigation drawer;
- top bar with theme, language, avatar menu, admin entry, and logout controls.

Real team switching, profile routes, notification counts, settings, active sessions, and team-scoped state clearing are implemented by the dependency-ordered roadmap phases for settings, sessions/active team, notifications, and module activation. Backend authorization primitives already exist after Phase 7 and are completed for UI visibility coverage in Phase 8.

The authenticated Inertia shell receives `auth.availableAdminRoutes` from the backend. The sidebar and top-bar Admin entry use that list only for visibility; protected Admin routes still require backend middleware authorization and password confirmation.

### Breadcrumbs

Every application and admin page uses centralized breadcrumbs through `diglactic/laravel-breadcrumbs`.

Breadcrumbs use:

- route names;
- translation keys;
- permissions;
- team context.

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

Fix inaccessible TailAdmin components rather than accepting defects.

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

The Inertia flash contract accepts queued messages with type, translation key or message text, optional translated description key, configurable timeout, and a critical flag for manual dismissal. Frontend events use `useToast` and render through `ToastViewport`.

### States and formatters

Use the shared `UiState` component for loading, empty, error, and no-results states.

Use shared frontend formatters from `resources/js/Utils/formatters.ts` for date, time, datetime, money, number, percent, status, and empty values. Money conversion uses integer minor units at component boundaries.

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

#
