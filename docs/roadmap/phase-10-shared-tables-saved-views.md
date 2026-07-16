## Phase 10 — Shared tables and saved views

**Status:** `not started`

## Objective

Complete the shared table framework and saved-view contracts before additional Admin and business screens build tabular workflows.

The Phase 7 Admin work created the first shared DataTable foundation for current screens. This phase completes the known table and saved-view contract for future screens. Report artifact generation, queued exports, PDF, charts, and browser print are intentionally moved to Phase 24, after files, notifications, audit, active-team context, and operational visibility are complete.

## Dependencies

- [Phase 8 — Foundation completion and roadmap dependency repair](phase-08-foundation-completion.md)
- [Phase 9 — Shared UI components](phase-09-shared-ui.md)
- [Frontend and shared UI architecture](../architecture/frontend-ui.md)
- [Tables, reports, exports, charts, and print](../architecture/tables-reports-exports-and-print.md)

## Implementation contract

- Every application table uses one shared TanStack Table wrapper.
- No module may build a separate table framework.
- Tables use server-side pagination, sorting, and filtering.
- Backend validates allowed columns, filters, sorting, and pagination.
- Query string uses stable English names and never stores sensitive values.
- Support column visibility, order, selection, grouping where justified, loading, empty, error, and no-results states.
- Saved views contain safe configuration only, never row data.
- Saved view types:
  - private;
  - team-shared;
  - system.
- A user may set a default view.
- A view may persist filters, sorting, columns, column order, grouping, and dynamic or fixed time ranges.
- Shared views require permission and are team-scoped.
- System views cannot be overwritten or deleted, but can be copied.
- Shared/system-view changes emit stable audit events from this phase. Until Phase 11 consolidates the full Audit module, the existing audit bridge records those events without changing the producer contract.
- The existing Phase 7 currently-loaded CSV/XLSX/PDF/print export actions remain allowed only as small Admin conveniences over the visible loaded dataset. They must not be treated as the final export/report lifecycle.

## Tasks

- [ ] Build or complete the shared TanStack Table wrapper for all current and planned table screens.
- [ ] Add server-side pagination.
- [ ] Add server-side sorting.
- [ ] Add server-side filtering.
- [ ] Add backend allowlists for columns, filters, and sorting.
- [ ] Add deterministic URL query synchronization.
- [ ] Add column visibility.
- [ ] Add column ordering.
- [ ] Add selection.
- [ ] Add loading, empty, error, and no-results states.
- [ ] Add private saved views.
- [ ] Add team-shared saved views.
- [ ] Add system views.
- [ ] Add default-view support.
- [ ] Add view copy.
- [ ] Prevent overwrite/delete of system views.
- [ ] Define stable audit events for shared-view changes and wire them to the current audit bridge without creating a later producer-contract change.
- [ ] Verify all table and saved-view states in light and dark themes.
- [ ] Add frontend, backend, and browser coverage for table state, authorization boundaries, and query-string determinism.
- [ ] Update architecture documentation for table versus report/export scope.
- [ ] Commit shared tables and saved views.

## Completion criteria

- [ ] New tabular screens can use the shared table/saved-view framework without local substitutes.
- [ ] Table state is deterministic, shareable where appropriate, and free of sensitive URL values.
- [ ] Backend validation covers columns, filters, sorting, pagination, and saved-view mutations.
- [ ] Full report/export/PDF artifact lifecycle work remains explicitly deferred to Phase 24 without leaving table gaps.
- [ ] Relevant tests and documentation are current.
