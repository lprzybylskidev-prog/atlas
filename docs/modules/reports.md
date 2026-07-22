# Reports module

Canonical current behavior for report/export request snapshots, generated artifact metadata, render credentials, and the shared report/export lifecycle.

## Reports

Reports is an optional module. It owns the shared lifecycle for CSV, XLSX, PDF, browser print, and future chart-backed reports.

The current implementation establishes the module boundary and persistence foundation:

- `ReportsModule` is deployed as the optional `reports` module.
- Report/export request snapshots are persisted in the `optional_reports` PostgreSQL schema.
- Request snapshots include report key, report name, owning module, format, active team, requesting user, filters, sorting, visible columns, column order, time range, release/rule version, expiry, and an authorization snapshot.
- Authorization snapshots are hashed into an authorization fingerprint and participate in the full request fingerprint.
- Identical report/export requests deduplicate only when the full request and authorization fingerprint match.
- Detailed audit/history exports set the immutable `audit_export` request flag, which participates in the request fingerprint and is reauthorized with `reports.audit-export` during generation, download, browser print, and render-token access.
- Synchronous execution eligibility is policy-owned and persisted on the snapshot only when the caller allowed it, a non-negative estimated row count is present, and configured row/cell thresholds are satisfied.
- CSV/XLSX synchronous thresholds are configured through `atlas.reports.synchronous_export_max_rows`, `atlas.reports.synchronous_export_max_cells`, and `atlas.reports.synchronous_timeout_seconds`.
- PDF synchronous execution is disabled by default through `atlas.reports.synchronous_pdf_enabled=false`; with the default policy all PDF artifacts are queued through managed processes.
- `ReportExportGenerationDispatcher::dispatchSnapshot()` records the authorized snapshot and selects the safe execution path. Synchronous CSV/XLSX exports generate through the same artifact generator, private storage, metadata publishing, and notifications as queued exports. Unsafe or PDF exports are queued as managed-process runs.
- Generated artifact metadata is owned by the Reports module and records status, content type, filename, checksum, size, creator, file object linkage, availability, failure, and expiry.
- Render credentials are owned by the Reports module and are bound to one export request, one requesting user, one team, one module/report key, one allowed dataset, one allowed column set, and one expiry window.
- Render credentials store only a SHA-256 token hash, resolve without a live authenticated browser session, reject expired or consumed credentials, and are consumed after successful rendering.
- Partial, failed, expired, or cancelled artifacts are not considered downloadable lifecycle states.
- PostgreSQL constraints prevent an `available` artifact without a file object, checksum, positive size, availability timestamp, and no failure timestamp.
- PostgreSQL allows at most one `available` artifact for one export request.
- Queued generation uses the managed-process definition `reports.export.generate` on the `reports` queue with `one_active_per_actor` concurrency and safe retry/cancel policy.
- Export request recording and generation process runs validate the immutable request snapshot, recheck the Reports module and owning module through ModuleGate, link the process run to the export request, and move the request through requested/queued/generating/failed states.
- Artifact downloads are served through `reports.download` at `/reports/exports/{artifact}/download`.
- Every artifact download reauthorizes the requesting user, active team, Reports module, owning module, request status, artifact status, audit-export permission where required, and expiry before returning a private file.
- Download delivery delegates clean-file checks and storage path resolution to the Files public `FileStorage` contract.
- Retention cleanup expires old report requests and artifacts, then deletes linked file objects through the Files public `FileLifecycle` contract.
- Operators can run `php artisan reports:cleanup-expired` to execute report/export retention cleanup.
- CSV and XLSX generation are available through the shared report/export generator registry for registered report data providers.
- CSV, XLSX, PDF, and browser-print output use the immutable request snapshot's filters, sorting, active team, visible columns, column order, and authorization snapshot.
- Tabular export generation intersects visible columns with authorization `allowed_columns` and provider columns before rendering, so request-visible but unauthorized columns are excluded.
- CSV, XLSX, PDF, and browser-print output include the shared baseline `Total rows` total computed from the final exported row set.
- CSV and XLSX output store the generated file privately through Files and publish a Reports artifact only after storage metadata is complete.
- CSV and XLSX cells are rendered from scalar, stringable, date, boolean, or null values; cells that could be interpreted as spreadsheet formulas are prefixed before export.
- XLSX output is generated with PhpSpreadsheet and writes report values as explicit strings rather than spreadsheet formulas.
- PDF generation renders the shared HTML report table layout through Playwright/Chromium, stores the PDF privately through Files, and publishes a Reports artifact only after storage metadata is complete.
- PDF rendering uses an internal short-lived render credential instead of a live authenticated browser session and consumes that credential after successful rendering.
- Render credential access rechecks Reports and owning-module availability through ModuleGate before rendering.
- Browser print is served through `reports.exports.print` at `/reports/exports/{export}/print` and reauthorizes the requesting user, active team, Reports module, owning module, request status, and expiry before returning the shared print layout.
- The shared PDF/browser-print layout embeds locally packaged Instrument Sans fonts as inline WOFF2 data and does not load Google Fonts or any external font URL during rendering.
- The current shared layout defines A4 output, margins, repeated table headers, controlled row page breaks, print-safe colors, report metadata, footer, total rows, Chromium PDF page numbering, and browser print page counters where supported by the print engine.
- Report header and company identity are centralized through `atlas.reports.company.*` configuration. The current layout supports company name, local logo path, and footer text.
- Reports with required asynchronous visuals can register `ReportRenderReadinessProbe` services tagged as `atlas.report_render_readiness_probes`.
- Reports with meaningful chart data can register `ReportChartProvider` services tagged as `atlas.report_chart_providers`; browser print and PDF render those charts as print-safe inline SVG from the same immutable request snapshot.
- PDF generation checks matching render-readiness probes before issuing a render credential; a not-ready result fails the export request and does not publish an artifact.
- Reports registers a module deactivation guard that blocks deactivating a module while that module owns requested, queued, or generating report/export requests.
- Successful and failed queued generation publishes requester notifications through the Notifications module in addition to managed-process progress updates.

The module exposes these public contracts for other modules and shared infrastructure without importing Reports internals:

- `ReportExportRequestRecorder` records authorized immutable request snapshots.
- `ReportExportGenerationDispatcher` starts queued generation for recorded requests and can record a snapshot while selecting synchronous or queued generation.
- `ReportExportArtifactAccess` reauthorizes and resolves downloadable artifacts.
- `ReportExportMaintenance` expires old requests/artifacts and performs retention cleanup.
- `ReportRenderCredentialIssuer` issues short-lived internal render credentials for one report/export request.
- `ReportRenderCredentialAccess` resolves and consumes short-lived internal render credentials.

Concrete Admin operational views are completed by the remaining Phase 24 tasks.

Permissions:

- `reports.request` allows requesting authorized report/export artifacts.
- `reports.download` allows reauthorized downloads of generated artifacts.
- `reports.exports.print` allows rendering authorized browser print layouts.
- `reports.audit-export` allows detailed audit/history exports instead of ordinary final-value exports.
- `admin.reports.index` allows viewing report/export lifecycle status in Admin operations.
