# Phase 24 — Reports, exports, PDF, charts, and print

**Status:** `complete`

## Objective

Build the shared report/export generation lifecycle after tables, saved views, files, notifications, audit, active-team context, and operational visibility are available.

## Dependencies

- [Phase 10 — Shared tables and saved views](phase-10-shared-tables-saved-views.md)
- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 13 — Sessions and active team](phase-13-sessions-active-team.md)
- [Phase 15 — Notifications and realtime foundation](phase-15-notifications-realtime.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Phase 19 — Files](phase-19-files.md)
- [Phase 20b — Managed processes, process logs, and scheduler](phase-20b-managed-processes.md)
- [Tables, reports, exports, charts, and print](../architecture/tables-reports-exports-and-print.md)

## Implementation contract

- Provide CSV, XLSX, PDF, and browser print through one shared lifecycle.
- Export/print uses exactly the active filters, sort, time range, visible columns, active team, and effective permissions.
- Backend rechecks every field; UI visibility is not authorization.
- Small exports may run synchronously. Large exports run through queues and notify when ready.
- Generated artifacts are stored privately with expiry and cleanup.
- PDF and print use a clean report layout, not a raw print of the application screen.
- PDF, XLSX, and print headers include report name, active team, filters, date range, generation timestamp, generating user, and totals.
- PDF and print include page numbering.
- Company identity, logo, and footer come from centralized report configuration.
- Ordinary exports show final values and meaningful markers. Detailed history uses a separate audit export permission.
- Charts use shared Atlas chart wrappers owned by the repository.
- Charts share the table's filters and data contract, supplement rather than replace the table, and may appear in PDF/print.
- Large ranges aggregate by day, week, or month.
- Do not add decorative analytics without a real interpretive purpose.
- Use HTML/CSS rendered by headless Chromium through Playwright as the shared PDF engine.
- Browser print and generated PDF use the same report layout and data contract.
- PDF rendering must not depend on the user's live authenticated browser session.
- A PDF render job receives access through a short-lived one-time render token or an equivalent internal signed mechanism.
- The render credential is bound to one concrete report/export request, requesting user, active team, allowed dataset and columns, and a short expiration time.
- A render credential must not grant access to unrelated reports or application data.
- Invalidate or consume the render credential after successful use.
- Package required fonts locally in the application or production image.
- Do not fetch Google Fonts or other external fonts during PDF generation.
- Define explicit report-print rules for A4, margins, controlled page breaks, repeated table headers, page numbers, report footer, company identity, and print-safe colors/contrast.
- Charts included in PDF must signal that rendering is complete before Chromium prints the document.
- If a required chart or other required visual fails to render, fail the PDF job rather than generating an incomplete artifact.
- Large PDFs always run through queues.
- Small PDFs may be synchronous only when explicit size and execution-time thresholds make it safe; queued generation is preferred for consistency.
- PDF generation failures are visible, retryable only when safe, audited where relevant, and reported through the shared notification/progress system.
- Generated reports/exports use managed-process runs for queued lifecycle visibility: authorize, snapshot request, select sync/queued path, generate idempotently with concurrency limits, store privately, notify, reauthorize download, expire.
- Retries never expose duplicate or partial artifacts.
- Concurrency limits are configured through managed-process policy per user, team, and report type.

## Tasks

- [x] Implement immutable report/export request snapshots and authorization fingerprints.
- [x] Implement idempotent generation jobs and managed-process concurrency controls.
- [x] Prevent partial or duplicate artifacts from becoming downloadable.
- [x] Store checksum, content type, size, creator, status, release/rule version, and expiry metadata.
- [x] Reauthorize every artifact download and implement retention cleanup.
- [x] Add CSV exports.
- [x] Add XLSX exports.
- [x] Add PDF exports.
- [x] Implement shared HTML/CSS report layouts for both browser print and PDF.
- [x] Implement headless Chromium/Playwright PDF rendering.
- [x] Implement short-lived one-time report render credentials bound to report, user, team, dataset, and allowed columns.
- [x] Ensure PDF rendering never depends on a live user browser session.
- [x] Package report fonts locally and prohibit network font loading during rendering.
- [x] Define A4, margins, repeated table headers, page-break, page-number, footer, and print-color rules.
- [x] Implement a render-ready contract for charts and other asynchronous visuals.
- [x] Fail PDF generation when required visuals do not finish rendering.
- [x] Queue all large PDFs and define safe thresholds for any synchronous PDF path.
- [x] Integrate PDF progress, failure, retry, storage, expiry, and notifications with shared reporting and managed-process infrastructure.
- [x] Add Chromium-based tests for multipage tables, repeated headers, charts, fonts, page numbers, and failure handling.
- [x] Add browser print layouts.
- [x] Make exports honor filters, sorting, visible columns, permissions, and active team.
- [x] Enforce ModuleGate for report views, export requests, queued export/PDF jobs, download authorization, and render-token access.
- [x] Register report/export deactivation guards for unsafe in-flight generation where a module owns report jobs.
- [x] Add synchronous small exports.
- [x] Add queued large exports as managed-process runs.
- [x] Add export-ready notifications.
- [x] Add storage expiry and cleanup.
- [x] Add centralized report header configuration.
- [x] Add company data, logo, and footer configuration.
- [x] Add report page numbering.
- [x] Add totals.
- [x] Add separate audit export permission.
- [x] Use project-owned chart wrappers instead of a third-party UI template dependency.
- [x] Build shared Atlas chart wrappers.
- [x] Add charts to PDF and print where justified.
- [x] Verify all report/chart/export/print states in light and dark themes.
- [x] Commit reports, exports, PDF, charts, and print.

## Completion criteria

- [x] Reports and exports use the shared lifecycle and managed-process visibility end to end.
- [x] Generated artifacts are private, authorized, expiring, and safe to retry.
- [x] PDF and browser print share the same report contract.
- [x] Progress and failures are visible through notifications and Admin operations.
- [x] Relevant tests and documentation are current.
