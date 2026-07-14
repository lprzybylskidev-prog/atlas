## Phase 15 — Shared UI components

### Implementation contract

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

- [ ] Build shared tooltip and popover system.
- [ ] Remove native `title` usage.
- [ ] Build shared modal system.
- [ ] Build shared confirmation system.
- [ ] Add destructive and typed-confirmation variants.
- [ ] Build shared alert/toast system.
- [ ] Add standardized Inertia flash contract.
- [ ] Add alert queue and progress bars.
- [ ] Build shared loading, empty, error, and no-results states.
- [ ] Build shared form controls.
- [ ] Add unsaved-change warning.
- [ ] Prevent double submission.
- [ ] Add shared backend field-error mapping.
- [ ] Add shared money input and formatter.
- [ ] Add date, time, money, percent, number, status, and empty-value formatters.
- [ ] Verify all components in light and dark themes.
- [ ] Add accessibility tests.
- [ ] Commit shared UI foundation.
