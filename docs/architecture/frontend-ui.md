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

The initial shell persists the selected theme in local storage as a temporary user-settings contract until typed backend user settings are implemented.

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
- unread notification count;
- profile;
- settings;
- team switch;
- active sessions;
- logout.

Team switching must be visible enough to avoid accidental context mistakes.

The initial review shell includes:

- `AuthLayout` for login;
- `AppLayout` for authenticated application screens;
- `AdminLayout` for authenticated administrative screens;
- collapsible desktop sidebar;
- mobile navigation drawer;
- top bar with theme, notification, settings, admin, active team, avatar, and logout controls.

The active team selector is currently a visible placeholder. Real team switching, backend authorization, and team-scoped state clearing are implemented in later identity, team, authorization, and settings phases.

### Breadcrumbs

Every application and admin page uses centralized breadcrumbs through `diglactic/laravel-breadcrumbs`.

Breadcrumbs use:

- route names;
- translation keys;
- permissions;
- team context.

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
- `Utils`

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

The active-team, permission, and module-gate requirements are metadata only until the later Authorization, Sessions/active-team, and Module Activation phases connect real backend enforcement.

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

### Forms

Use `novalidate`.

Backend validation is the source of truth.

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

Requirements:

- common loading, disabled, success, and error states;
- prevent double submission;
- warn about unsaved changes;
- common reset behavior;
- permission-aware UI;
- map backend field errors;
- money input converts to backend minor units through one shared formatter.

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
