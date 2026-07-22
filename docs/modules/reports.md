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
- Generated artifact metadata is owned by the Reports module and records status, content type, filename, checksum, size, creator, file object linkage, availability, failure, and expiry.
- Render credentials are owned by the Reports module and are bound to one export request, one requesting user, one team, one module/report key, one allowed dataset, one allowed column set, and one expiry window.
- Partial, failed, expired, or cancelled artifacts are not considered downloadable lifecycle states.
- PostgreSQL constraints prevent an `available` artifact without a file object, checksum, positive size, availability timestamp, and no failure timestamp.
- PostgreSQL allows at most one `available` artifact for one export request.
- Queued generation uses the managed-process definition `reports.export.generate` on the `reports` queue with `one_active_per_actor` concurrency and safe retry/cancel policy.
- Generation process runs validate the immutable request snapshot, recheck the Reports module and owning module through ModuleGate, link the process run to the export request, and move the request through queued/generating/failed states.
- Until concrete format generators are registered, the generation handler fails closed and does not publish an artifact.

The module exposes `ReportExportRequestRecorder` as the public contract for other modules to record authorized immutable request snapshots without importing Reports infrastructure.

Concrete CSV/XLSX/PDF generation, downloads, cleanup, notifications, browser print, Chromium PDF rendering, one-time credential consumption, and Admin operational views are completed by the remaining Phase 24 tasks.

Permissions:

- `reports.request` allows requesting authorized report/export artifacts.
- `reports.download` allows reauthorized downloads of generated artifacts.
- `reports.audit-export` allows detailed audit/history exports instead of ordinary final-value exports.
- `admin.reports.index` allows viewing report/export lifecycle status in Admin operations.
