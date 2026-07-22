# Phase 24a — Core export foundation and Admin data integration

**Status:** `complete`

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

## Admin data surface inventory

This inventory is binding for Phase 24a export integration. Every listed surface must either register a Core Exports provider during the Admin integration tasks or keep the documented non-exportable rationale current.

### Shared DataTable surfaces

| Surface | Route | Vue page | Backend table state | Owning module | Export classification | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Users | `admin.users.index` | `Admin/Users/Index` | `AdminTableDefinitions::USERS` | `users` | Exportable | Final account list values; exclude secrets and action-only state. |
| Teams | `admin.teams.index` | `Admin/Teams/Index` | `AdminTableDefinitions::TEAMS` | `teams` | Exportable | Final team list values. |
| Roles | `admin.authorization.roles.index` | `Admin/Authorization/Roles` | `AdminTableDefinitions::ROLES` | `authorization` | Exportable | Final role catalog values and counts. |
| Authorization presets | `admin.authorization.packages.index` | `Admin/Authorization/Packages` | `AdminTableDefinitions::PACKAGES` | `authorization` | Exportable | Final onboarding package values. |
| Permissions | `admin.authorization.permissions.index` | `Admin/Authorization/Permissions` | `AdminTableDefinitions::PERMISSIONS` | `authorization` | Exportable | Permission catalog values; no mutable action state. |
| Audit events | `admin.audit.index` | `Admin/Audit/Index` | `AdminTableDefinitions::AUDIT` | `audit` | Exportable with audit-export distinction | Ordinary export shows final/safe values; detailed metadata requires `exports.audit-export`. |
| Rate-limit policies | `admin.rate-limits.index` | `Admin/RateLimits/Index` | `AdminTableDefinitions::RATE_LIMITS` | `identity` | Exportable | Policy and aggregate rejection statistics only; no limiter reset action state. |
| Module activation overview | `admin.modules.index` | `Admin/Modules/Index` | `AdminTableDefinitions::MODULES` | `authorization` | Exportable | Deployed module state and activation support summary. |
| Manager relationship history | `admin.managers.index` | `Admin/Managers/Index` | Page-local table data | `teams` | Exportable | Relationship history table; create/preview/end forms are not exportable. |
| Security history | `admin.audit.security-history.index` | `Admin/Audit/SecurityHistory` | Page-local filtered rows | `audit` | Exportable with audit-export distinction | Security event rows; detailed context requires `exports.audit-export`. |
| Impersonation session events | `admin.audit.impersonation.show` | `Admin/Audit/ImpersonationSession` | Page-local rows | `audit` | Exportable with audit-export distinction | Session event rows; session summary card is not tabular. |
| Files | `admin.files.index` | `Admin/Files/Index` | Page-local filtered rows | `files` | Exportable | File object operational fields; no storage paths or scan payload internals beyond existing safe columns. |
| Integration adapter status | `admin.integrations.index` | `Admin/Integrations/Index` | Page-local rows | `integrations` | Exportable | Adapter status, timestamps, circuit state, and safe error summaries. |
| Integration synchronization runs | `admin.integrations.index` | `Admin/Integrations/Index` | Page-local rows | `integrations` | Exportable | Run status/statistics; no credentials or raw external payloads. |
| Feature flags | `admin.feature-flags.index` | `Admin/FeatureFlags/Index` | Page-local rows | `feature_flags` | Exportable | Registered flag keys, effective state, source, and safe descriptions. |
| Feature flag history | `admin.feature-flags.index` | `Admin/FeatureFlags/Index` | Page-local rows | `feature_flags` | Exportable | Historical changes; reason/actor fields are exportable audit-like values. |
| Search index descriptors | `admin.search.index` | `Admin/Search/Index` | Page-local rows | `search` | Exportable | Index readiness and descriptor values. |
| Search rebuild runs | `admin.search.index` | `Admin/Search/Index` | Page-local rows | `search` | Exportable | Managed-process-linked rebuild summaries only. |
| Managed-process runs | `admin.managed-processes.index` | `Admin/ManagedProcesses/Runs` | Page-local filtered rows | `managed_processes` | Exportable | Run summary, status, progress, actor/team, and timestamps. |
| Import executions | `admin.managed-processes.imports.index` | `Admin/ManagedProcesses/Imports` | Page-local rows | `imports` | Exportable | Import execution summary and counts. |
| Managed-process definitions | `admin.managed-processes.definitions.index` | `Admin/ManagedProcesses/Definitions` | Page-local rows | `managed_processes` | Exportable | Definition catalog and operational policy fields; no manual-run form state. |
| Managed-process schedules | `admin.managed-processes.schedules.index` | `Admin/ManagedProcesses/Schedules` | Page-local rows | `managed_processes` | Exportable | Schedule catalog and safe status fields; no disable action state. |
| Managed-process import row errors | `admin.managed-processes.show` | `Admin/ManagedProcesses/Show` | Page-local rows | `managed_processes` / `imports` | Exportable | Row-error table for a concrete run; scoped to the visible run. |

### Custom Admin data surfaces

| Surface | Route | Vue page | Owning module | Export classification | Rationale |
| --- | --- | --- | --- | --- | --- |
| Application logs | `admin.logs.index` | `Admin/Logs/Index` | `authorization` | Exportable with redacted log provider | The visible list is operationally useful, but exports must use the existing redacted fields only and must not expose filesystem paths, raw secrets, or unbounded stack payloads. |
| Failed jobs | `admin.queues.index` | `Admin/Queues/Index` | `authorization` | Exportable with restricted provider | Export safe failed-job summary fields only; raw payload and full exception text stay out of ordinary exports. |
| Module detail teams | `admin.modules.show` | `Admin/Modules/Show` | `authorization` | Exportable | Team activation rows for one module. |
| Module detail history | `admin.modules.show` | `Admin/Modules/Show` | `authorization` | Exportable | Activation history rows for one module. |
| Module detail schedules | `admin.modules.show` | `Admin/Modules/Show` | `authorization` | Exportable | Scheduled activation rows for one module. |
| Admin dashboard status cards | `admin.system-status` | `Admin/SystemStatus` | `authorization` / `health` | Not exportable in Phase 24a | Status cards are operational signals, not a stable tabular dataset. Dedicated health snapshots can be added later with a separate provider contract if needed. |
| System-status partial endpoints | `admin.system-status.*` | Composable dashboard endpoints | `health` / owning signal module | Not exportable in Phase 24a | Partial JSON feeds are for dashboard rendering and do not expose a coherent user-facing table. |
| User, team, role, preset, module, feature-flag, queue, and process action forms | Multiple Admin routes | Multiple pages | Owning module | Not exportable | Forms and mutating action controls are workflows, not tabular data surfaces. |
| Pulse | `admin.pulse.view` | External package UI | `authorization` | Not Atlas-exportable | Package-owned external diagnostics UI outside Atlas DataTable/custom data contracts. |
| Telescope | `admin.telescope.view` | External package UI | `authorization` | Not Atlas-exportable | Local/development-only package UI outside Atlas DataTable/custom data contracts. |

## Tasks

- [x] Define the Core export module or shared Core namespace and document ownership boundaries.
- [x] Move reusable Report export contracts, DTOs, services, generators, render credentials, render readiness, artifact access, cleanup, and console command ownership into Core.
- [x] Keep optional Reports as a consumer of the Core export foundation for named reports and report-specific chart providers.
- [x] Rename service provider bindings, container tags, permissions, routes, commands, config keys, tests, and documentation from report-owned export lifecycle to Core export lifecycle where appropriate.
- [x] Preserve PostgreSQL schema ownership or migrate tables explicitly so generated export state belongs to Core while optional Reports stores only report-specific metadata.
- [x] Add Admin DataTable export provider contracts and connect shared table state to immutable export snapshots.
- [x] Inventory every Admin DataTable and custom Admin data presentation.
- [x] Add CSV, XLSX, PDF, and browser print actions to all exportable Admin DataTables.
  - Shared `DataTable` now supports backend-provided export metadata and posts CSV/XLSX/PDF/browser-print requests to the Core Exports Admin DataTable endpoint.
  - Shared `DataTable` now accepts explicit page-local filter state and includes it in export snapshots, so local Admin filter panels can be preserved during export.
  - Core Exports now has an Admin DataTable provider registry and maps tagged Admin DataTable providers into the ordinary export data provider registry.
  - Users, Teams, Roles, Authorization presets, Permissions, Audit events, Security history, Rate-limit policies, and Module activation overview now register Admin DataTable export providers and expose CSV/XLSX/PDF/browser-print actions.
  - Ordinary Audit events exports exclude raw `metadata`; detailed metadata exports remain pending for the separate `exports.audit-export` path.
- [x] Add export/print integration to exportable custom Admin data surfaces such as operational lists, process/history views, audit listings, files, queues, and diagnostics.
  - Files, Integration adapter status, Integration synchronization runs, Search index descriptors, and Search rebuild runs now expose CSV/XLSX/PDF/browser-print actions through page-local DataTable export metadata.
  - Feature flags, Feature flag history, Managed-process runs, Import executions, Managed-process definitions, and Managed-process schedules now expose CSV/XLSX/PDF/browser-print actions through page-local DataTable export metadata.
  - Application logs, Failed jobs, Module detail teams, Module detail history, and Module detail schedules now expose CSV/XLSX/PDF/browser-print actions through dedicated safe export providers.
  - Manager relationship history, Impersonation session events, and Managed-process import row errors now expose CSV/XLSX/PDF/browser-print actions scoped to the selected team, session, or run.
  - Page-local DataTable exports can pass an explicit `export-key` and export metadata without converting the whole surface to server-driven saved views.
- [x] Document non-exportable Admin surfaces with explicit rationale when export/print would be misleading, unsafe, or operationally useless.
- [x] Ensure every Admin export rechecks backend permissions, active team, ModuleGate, visible columns, filters, sorting, and time range.
  - `admin.exports.data-table` is covered through endpoint-level integration tests that verify Admin mode/permission middleware reachability, ModuleGate rejection for the owning data module, active-team capture, scalar filter preservation, search sanitization, invalid sort fallback, invalid column exclusion, column ordering, queued managed-process linkage, and immutable authorization snapshots.
- [x] Preserve CSV, XLSX, PDF, browser print, local fonts, page numbering, chart readiness, private artifact storage, notifications, managed-process visibility, expiry, cleanup, and retry behavior after the move.
- [x] Remove stale Optional Reports export-engine leftovers, duplicate classes, obsolete tags, and outdated documentation.
  - Core Exports owns the deactivation guard and cleanup command classes; `exports:cleanup-expired` remains the operational command name.
  - `ATLAS_EXPORT_*` environment variables are canonical for export configuration, with pre-production compatibility fallback to existing `ATLAS_REPORT_*` variables.
- [x] Add or update unit, integration, feature, Vitest, and Playwright coverage for Core export ownership and Admin table export workflows.
- [x] Run required quality gates and update canonical docs.
  - Relevant integration/feature tests passed for Core Exports, Reports integration, managed processes, audit, Admin mode, impersonation, and manager hierarchy.
  - `composer lint && composer analyse` passed after the Core export extraction and Admin DataTable export integration.
- [x] Commit Core export foundation extraction and Admin data integration.

## Completion criteria

- [x] Export lifecycle ownership is Core/shared and optional Reports depends on it instead of owning it.
- [x] All exportable Admin DataTables and custom Admin data surfaces use the shared Core export foundation or have documented non-exportable rationale.
- [x] No duplicate optional-report-only export engine remains.
- [x] Reports module documentation describes only Reports-owned behavior and its Core export integration points.
- [x] Relevant tests and documentation are current.
- [x] `WORKROAD.md` points to the next correct active phase.
