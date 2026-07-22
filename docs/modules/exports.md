# Exports

Canonical current behavior for the Core export foundation: export request snapshots, artifact lifecycle metadata, render credentials, PDF/browser print rendering, and shared export provider contracts.

## Ownership

Exports is a Core platform module. It owns the reusable lifecycle for CSV, XLSX, PDF, browser print, report-style table layouts, generated artifact access, render credentials, render readiness checks, cleanup, and export provider registries.

Optional Reports depends on Exports for named reports, report-specific charts, catalogs, and future business reporting workflows. Admin and business modules register typed data providers with Exports; Exports must not query another module's tables directly.

The current implementation preserves the Phase 24 lifecycle while moving ownership out of optional Reports:

- `ExportsModule` is deployed as the Core `exports` module.
- Export request snapshots are persisted in the `core_exports` PostgreSQL schema.
- Request snapshots include report key, report name, owning module, format, active team, requesting user, filters, sorting, visible columns, column order, time range, release/rule version, expiry, and an authorization snapshot.
- Authorization snapshots are hashed into an authorization fingerprint and participate in the full request fingerprint.
- Identical export requests deduplicate only when the full request and authorization fingerprint match.
- Detailed audit/history exports set the immutable `audit_export` request flag, which participates in the request fingerprint and is reauthorized with `exports.audit-export` during generation, download, browser print, and render-token access.
- Synchronous CSV/XLSX thresholds are configured through `atlas.exports.synchronous_export_max_rows`, `atlas.exports.synchronous_export_max_cells`, and `atlas.exports.synchronous_timeout_seconds`. Environment overrides use `ATLAS_EXPORT_*` names; legacy `ATLAS_REPORT_*` variables are accepted only as pre-production compatibility fallbacks.
- PDF synchronous execution is disabled by default through `atlas.exports.synchronous_pdf_enabled=false`; with the default policy all PDF artifacts are queued through managed processes.
- `ReportExportGenerationDispatcher::dispatchSnapshot()` records the authorized snapshot and selects the safe execution path. Synchronous CSV/XLSX exports generate through the same artifact generator, private storage, metadata publishing, and notifications as queued exports. Unsafe or PDF exports are queued as managed-process runs.
- Generated artifact metadata records status, content type, filename, checksum, size, creator, file object linkage, availability, failure, and expiry.
- Render credentials are bound to one export request, one requesting user, one team, one owning module/report key, one allowed dataset, one allowed column set, and one expiry window.
- Render credentials store only a SHA-256 token hash, resolve without a live authenticated browser session, reject expired or consumed credentials, and are consumed after successful rendering.
- Partial, failed, expired, or cancelled artifacts are not downloadable lifecycle states.
- PostgreSQL constraints prevent an `available` artifact without a file object, checksum, positive size, availability timestamp, and no failure timestamp.
- PostgreSQL allows at most one `available` artifact for one export request.
- Queued generation uses the managed-process definition `exports.generate` on the `exports` queue with `one_active_per_actor` concurrency and safe retry/cancel policy.
- Export request recording and generation process runs validate the immutable request snapshot, recheck the Exports module and owning module through ModuleGate, link the process run to the export request, and move the request through requested/queued/generating/failed states.
- Artifact downloads are served through the authenticated `exports.download` route at `/exports/{artifact}/download`; route middleware authenticates the session and the Exports application service performs artifact-specific authorization.
- Every artifact download reauthorizes the requesting user, active team, Exports module, owning module, request status, artifact status, audit-export permission where required, and expiry before returning a private file.
- Download delivery delegates clean-file checks and storage path resolution to the Files public `FileStorage` contract.
- Retention cleanup expires old export requests and artifacts, then deletes linked file objects through the Files public `FileLifecycle` contract.
- Operators can run `php artisan exports:cleanup-expired` to execute export retention cleanup.
- CSV and XLSX generation are available through the shared export generator registry for registered data providers.
- CSV, XLSX, PDF, and browser-print output use the immutable request snapshot's filters, sorting, active team, visible columns, column order, and authorization snapshot.
- Tabular export generation intersects visible columns with authorization `allowed_columns` and provider columns before rendering, so request-visible but unauthorized columns are excluded.
- CSV, XLSX, PDF, and browser-print output include the shared baseline `Total rows` total computed from the final exported row set.
- CSV and XLSX cells are rendered from scalar, stringable, date, boolean, or null values; cells that could be interpreted as spreadsheet formulas are prefixed before export.
- XLSX output is generated with PhpSpreadsheet and writes values as explicit strings rather than spreadsheet formulas.
- PDF generation renders the shared HTML report table layout through Playwright/Chromium, stores the PDF privately through Files, and publishes an artifact only after storage metadata is complete.
- PDF rendering uses an internal short-lived render credential instead of a live authenticated browser session and consumes that credential after successful rendering.
- Render credential access rechecks Exports and owning-module availability through ModuleGate before rendering.
- Browser print is served through the authenticated `exports.print` route at `/exports/{export}/print` and reauthorizes the requesting user, active team, Exports module, owning module, request status, and expiry before returning the shared print layout.
- Admin DataTable export requests are accepted through `admin.exports.data-table` at `/admin/exports/data-table`. The endpoint validates the shared table payload, resolves a registered Admin table provider by `table_key`, rechecks Admin mode and backend route permission through middleware, rebuilds the immutable export snapshot, and then dispatches it through the same synchronous/queued export lifecycle as named reports. Snapshot rebuilding revalidates active team, ModuleGate availability, filters, search, sorting, visible columns, and column order before persistence.
- Shared Admin DataTables expose export controls by passing `AdminDataTableExportMeta::defaults()` into `TableResult::tableMeta()`. Page-local filter panels pass their current filter state into the shared `DataTable`, which includes those filters in the export request payload.
- The shared PDF/browser-print layout embeds locally packaged Instrument Sans fonts as inline WOFF2 data and does not load Google Fonts or any external font URL during rendering.
- The current shared layout defines A4 output, margins, repeated table headers, controlled row page breaks, print-safe colors, report metadata, footer, total rows, Chromium PDF page numbering, and browser print page counters where supported by the print engine.
- Export header and company identity are centralized through `atlas.exports.company.*` configuration. The current layout supports company name, local logo path, and footer text.
- Data surfaces with required asynchronous visuals can register `ReportRenderReadinessProbe` services tagged as `atlas.export_render_readiness_probes`.
- Data surfaces with meaningful chart data can register `ReportChartProvider` services tagged as `atlas.export_chart_providers`; browser print and PDF render those charts as print-safe inline SVG from the same immutable request snapshot.
- Admin tables that use the shared backend `TableState` register `AdminDataTableExportProvider` implementations. The provider exposes the table key, display name, owning module, request permission, rule version, `TableDefinition`, and backend-authorized export columns.
- `AdminDataTableExportProviderRegistry` resolves providers tagged as `atlas.admin_data_table_export_providers`; Exports also exposes those providers through the ordinary `ReportExportDataProviderRegistry` so CSV/XLSX/PDF/browser-print generation uses one provider contract.
- `AdminDataTableExportSnapshotFactory` maps backend-validated `TableState` plus active team, actor, filters, time range, and estimated row count into an immutable export snapshot. It revalidates sorting, visible columns, and column order against the provider's `TableDefinition` and authorized column set before recording the snapshot.
- The first registered shared Admin DataTable providers cover Users, Teams, Roles, Authorization presets, Permissions, Audit events, Security history, Rate-limit policies, and Module activation overview. Ordinary Audit events exports exclude raw `metadata`; detailed metadata exports remain separate until the `exports.audit-export` path is wired with distinct authorization behavior.
- Page-local Admin DataTables and operational card lists can expose exports with an explicit table key and export metadata while still using local filter panels and local row composition. The current page-local providers cover Files, Integration adapter status, Integration synchronization runs, Search index descriptors, Search rebuild runs, Feature flags, Feature flag history, Managed-process runs, Import executions, Managed-process definitions, Managed-process schedules, Application logs, Failed jobs, Module detail teams, Module detail history, Module detail schedules, Manager relationship history, Impersonation session events, and Managed-process import row errors.
- PDF generation checks matching render-readiness probes before issuing a render credential; a not-ready result fails the export request and does not publish an artifact.
- Exports registers a module deactivation guard that blocks deactivating a module while that module owns requested, queued, or generating export requests.
- Successful and failed queued generation publishes requester notifications through the Notifications module in addition to managed-process progress updates.

Public contracts exposed for other modules:

- `ReportExportRequestRecorder` records authorized immutable request snapshots.
- `ReportExportGenerationDispatcher` starts queued generation for recorded requests and can record a snapshot while selecting synchronous or queued generation.
- `ReportExportArtifactAccess` reauthorizes and resolves downloadable artifacts.
- `ReportExportMaintenance` expires old requests/artifacts and performs retention cleanup.
- `ReportRenderCredentialIssuer` issues short-lived internal render credentials for one export request.
- `ReportRenderCredentialAccess` resolves and consumes short-lived internal render credentials.
- `AdminDataTableExportProvider` exposes an Admin DataTable as a Core Exports data provider.

Permissions:

- `exports.request` allows requesting authorized export artifacts.
- `exports.download` allows reauthorized downloads of generated artifacts.
- `exports.print` allows rendering authorized browser print layouts.
- `exports.audit-export` allows detailed audit/history exports instead of ordinary final-value exports.
- `admin.exports.index` allows viewing export lifecycle status in Admin operations.
- `admin.exports.data-table` allows requesting exports from registered Admin DataTables.
