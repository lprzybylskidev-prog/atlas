## Phase 9 — Shared UI components

**Status:** `complete`

## Objective

Complete the shared UI primitive layer before future settings, audit, session, module activation, manager, Admin, and business screens need forms, confirmations, alerts, or local interaction substitutes.

## Dependencies

- [Phase 8 — Foundation completion and roadmap dependency repair](phase-08-foundation-completion.md)
- [Frontend and shared UI architecture](../architecture/frontend-ui.md)

## Implementation contract

Partial pull-forward note: Phase 7 Admin table work implemented the minimum shared modal, toast, DataTable i18n, selection, export-scope, and bulk-action foundations required by current Admin screens. Phase 8 verifies that current use. The remaining Phase 9 items stay open until the full shared UI foundation, destructive/typed confirmation variants, form components, formatters, accessibility coverage, and theme verification are completed.

- Build shared components before business modules need local substitutes.
- Reuse or extend an existing component before creating another.
- Avoid duplicates while also avoiding god-components; extract a stable common core with focused variants/adapters.
- One shared tooltip/popover system; no native `title`.
- One shared modal/confirmation system; no `window.confirm` or `window.alert`.
- Modals support focus trap, Escape, focus restoration, accessible labels, and avoid modal-on-modal flows.
- Destructive confirmation shows the exact object, irreversibility, affected count, and stronger typed confirmation for dangerous or mass operations.
- One shared alert/toast system for backend Inertia flash and frontend events.
- Alerts use translation keys and support success/info/warning/error, close, configurable timeout, multiple queued messages, accessible announcements, and a bottom progress bar whose appearance follows alert type.
- Critical alerts remain longer or require manual dismissal.
- Forms use `novalidate`; backend is the source of truth.
- Shared form components cover text, textarea, select, multiselect, checkbox, radio, date, datetime, money, upload, autocomplete, entity search, errors, and buttons.
- Prevent double submit, support common reset, warn on unsaved changes, show consistent loading/disabled/error/success states, and map backend field errors.
- Money input and display always use shared minor-unit conversion and formatting.
- Shared formatters cover date, time, money, percentages, numbers, status, and empty values.
- Every component must meet accessibility and light/dark requirements.

## Tasks

- [x] Build shared tooltip and popover system.
- [x] Remove native `title` usage.
- [x] Build shared modal system.
- [x] Build shared confirmation system.
- [x] Add destructive and typed-confirmation variants.
- [x] Build shared alert/toast system.
- [x] Add standardized Inertia flash contract.
- [x] Add alert queue and progress bars.
- [x] Build shared loading, empty, error, and no-results states.
- [x] Build shared form controls.
- [x] Add unsaved-change warning.
- [x] Prevent double submission.
- [x] Add shared backend field-error mapping.
- [x] Add shared money input and formatter.
- [x] Add date, time, money, percent, number, status, and empty-value formatters.
- [x] Verify all components in light and dark themes.
- [x] Add accessibility tests.
- [x] Commit shared UI foundation.

## Completion criteria

- [x] Future screens can use shared primitives for forms, modals, confirmations, alerts, toasts, loading, empty, error, and formatting without local substitutes.
- [x] No native `title`, `window.confirm`, or `window.alert` remains in ordinary application/Admin UI.
- [x] Light/dark and accessibility coverage exists for all shared primitives.
- [x] Relevant documentation is current.
