# Phase 24a — Core export foundation and Admin data integration

**Status:** `active`

## Objective

Extract the reusable export, PDF, print, artifact, and render lifecycle from the optional Reports module into a Core export foundation, then add export/print support to every exportable Admin DataTable and custom Admin data surface without making Admin depend on optional Reports.

## Dependencies

- [Phase 10 — Shared tables and saved views](phase-10-shared-tables-saved-views.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Phase 19 — Files](phase-19-files.md)
- [Phase 20b — Managed processes, process logs, and scheduler](phase-20b-managed-processes.md)
- [Phase 24 — Reports, exports, PDF, charts, and print](phase-24-reports-exports-print.md)
- [Reports, exports, PDF, charts, and print architecture](../architecture/tables-reports-exports-and-print.md)
- [Reports module](../modules/reports.md)

## Related documentation

- Module: `../modules/reports.md`
- Architecture: `../architecture/tables-reports-exports-and-print.md`
- Architecture: `../architecture/frontend-ui.md`
- Operations: `../operations/quality-gates-and-git.md`

## Implementation contract

- Export generation is a Core platform capability, not an optional Reports dependency.
- Reports remains an optional module for named reports, report-specific charts, report catalogs, and future business reporting workflows.
- Move reusable export lifecycle code, DTOs, contracts, render credentials, render readiness, artifact access, retention cleanup, CSV/XLSX/PDF generators, HTML/print layout services, and provider registries into an explicitly named Core export module or shared Core namespace.
- Keep module-owned data access in the owning module through typed provider contracts; the Core export foundation must not query another module's tables directly.
- Preserve the Phase 24 behavior while changing ownership: immutable snapshots, authorization fingerprints, ModuleGate checks, active-team scope, visible-column enforcement, managed-process visibility, notifications, private Files storage, expiry, cleanup, retry safety, and Chromium rendering.
- Admin DataTable export actions must use the same backend snapshot contract as generated reports.
- Every Admin DataTable and custom Admin data surface that displays exportable tabular data must either register an export provider or document why export/print is intentionally unsupported.
- Exportable Admin surfaces include shared DataTable instances and custom data presentations such as operational lists, process/history views, audit listings, file lists, queue/status lists, and diagnostics tables when their data is suitable for user-facing export.
- Export controls must be permission-aware on the backend; UI visibility is ergonomics only.
- Admin export actions must expose CSV, XLSX, PDF, and browser print only when the surface's backend provider and permissions support the requested format.
- Existing Admin table filters, sorting, visible columns, active team, and effective permissions must be preserved exactly in export snapshots.
- PDF and browser print for Admin data surfaces must use the shared report layout contract and local fonts.
- Chart support stays shared where the data surface registers a chart provider; charts remain supplemental and must not be decorative.
- Avoid compatibility shims that leave duplicate Optional Reports and Core export implementations behind.
- Rename namespaces, service tags, config keys, docs, tests, and operational commands so ownership is clear and no stale optional-report-only export wording remains.
- Migration strategy must preserve existing development data and schema ownership expectations before production deployment.

## Tasks

- [ ] Define the Core export module or shared Core namespace and document ownership boundaries.
- [ ] Move reusable Report export contracts, DTOs, services, generators, render credentials, render readiness, artifact access, cleanup, and console command ownership into Core.
- [ ] Keep optional Reports as a consumer of the Core export foundation for named reports and report-specific chart providers.
- [ ] Rename service provider bindings, container tags, permissions, routes, commands, config keys, tests, and documentation from report-owned export lifecycle to Core export lifecycle where appropriate.
- [ ] Preserve PostgreSQL schema ownership or migrate tables explicitly so generated export state belongs to Core while optional Reports stores only report-specific metadata.
- [ ] Add Admin DataTable export provider contracts and connect shared table state to immutable export snapshots.
- [ ] Inventory every Admin DataTable and custom Admin data presentation.
- [ ] Add CSV, XLSX, PDF, and browser print actions to all exportable Admin DataTables.
- [ ] Add export/print integration to exportable custom Admin data surfaces such as operational lists, process/history views, audit listings, files, queues, and diagnostics.
- [ ] Document non-exportable Admin surfaces with explicit rationale when export/print would be misleading, unsafe, or operationally useless.
- [ ] Ensure every Admin export rechecks backend permissions, active team, ModuleGate, visible columns, filters, sorting, and time range.
- [ ] Preserve CSV, XLSX, PDF, browser print, local fonts, page numbering, chart readiness, private artifact storage, notifications, managed-process visibility, expiry, cleanup, and retry behavior after the move.
- [ ] Remove stale Optional Reports export-engine leftovers, duplicate classes, obsolete tags, and outdated documentation.
- [ ] Add or update unit, integration, feature, Vitest, and Playwright coverage for Core export ownership and Admin table export workflows.
- [ ] Run required quality gates and update canonical docs.
- [ ] Commit Core export foundation extraction and Admin data integration.

## Completion criteria

- [ ] Export lifecycle ownership is Core/shared and optional Reports depends on it instead of owning it.
- [ ] All exportable Admin DataTables and custom Admin data surfaces use the shared Core export foundation or have documented non-exportable rationale.
- [ ] No duplicate optional-report-only export engine remains.
- [ ] Reports module documentation describes only Reports-owned behavior and its Core export integration points.
- [ ] Relevant tests and documentation are current.
- [ ] `WORKROAD.md` points to the next correct active phase.
