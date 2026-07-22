# Tables, reports, exports, charts, and print

Canonical shared contract for data tables, query strings, saved views, exports, report headers, charts, report generation, browser print, and PDF rendering.

## Tables, Reports, Exports, Charts, and Print

Every table uses the shared TanStack Table wrapper.

The Phase 10 shared `DataTable` wrapper is the only application table framework. Current Admin tables use backend-validated table state, server-side pagination, sorting, and filtering, deterministic English query-string keys, column visibility/order state, row selection for the currently loaded page, loading/empty/error/no-results states, row actions, and local CSV/XLSX/PDF/print actions for the currently loaded visible dataset.

Tables keep readable minimum widths for data cells and row actions. When the visible column set is wider than the available viewport, the shared wrapper uses horizontal scrolling instead of compressing columns until values or actions overlap. Truncated data cells expose the full formatted value through the shared tooltip pattern while keeping the formatted value selectable for normal browser copy operations.

Row actions that need confirmation use the shared modal flow through `DataTableAction.confirm`, including row-specific confirmation copy when needed. Pages must not implement separate row-action confirmation dialogs, native browser confirmations, or local action icon/button styling for normal tabular rows.

Saved views persist safe table configuration only: search/filter state, sorting, visible columns, column order, grouping keys, and fixed or dynamic time-range metadata. They never persist row data. Private views are owner-scoped, team-shared views are active-team scoped, and system views are read-only from the normal table UI. System views may be copied into private or team-shared views. Shared/system view changes are recorded through the current security audit bridge until the full Phase 11 Audit module exists.

Phase 24 implements the later report/export/PDF/chart/print artifact lifecycle after files, notifications, audit, active-team context, and operational visibility exist.

Admin table data columns place `public_id` first. Admin tables expose all safe non-secret columns from their backing table through the column visibility menu, while default visibility stays limited to the most operationally important fields. Secret values such as passwords, remember tokens, authentication tokens, MFA secrets, and recovery codes are never exposed as table columns.

It must support:

- server-side pagination;
- server-side sorting;
- server-side filtering;
- allowed-column validation;
- visible columns;
- column ordering;
- selection;
- loading;
- empty state;
- error state;
- no-results state;
- URL query synchronization;
- saved views;
- exports;
- print.

### Query strings

Filters, sorting, page, and search use stable English query names.

Do not put sensitive information in URLs.

Backend validates all requested filters, columns, sorting, pagination, saved-view state, and saved-view mutations.

### Saved views

Support:

- private views;
- team-shared views;
- system views;
- default view;
- filters;
- sorting;
- visible columns;
- column order;
- time range;
- grouping.

Sharing requires permission.

System views:

- cannot be deleted;
- cannot be overwritten;
- may be copied into private or team-shared views;
- may be managed centrally where justified.

Shared-view changes are audited.

### Exports

The report/export phase supports:

- CSV;
- XLSX;
- PDF;
- browser print.

Exports and print must honor:

- filters;
- sorting;
- selected time range;
- visible columns;
- permissions;
- active team.

Small exports may run synchronously.

Large exports use managed-process queues and notify the user when ready. This depends on the managed-process, notification, and operational-health foundations rather than inventing a local progress mechanism.

Use storage with expiry and cleanup.

### Report headers

PDF, XLSX, and print include:

- report name;
- active team;
- applied filters;
- date range;
- generation timestamp;
- generating user;
- totals.

PDF and print include page numbers.

Company data, logo, and footer come from centralized report configuration.

### Charts

Use TailAdmin Pro charts first after license confirmation.

Charts:

- use the same filters as the table;
- supplement, not replace, tabular data;
- may be included in PDF and print;
- aggregate large ranges by day, week, or month;
- must have an actual analytical purpose.

---

### Report generation pipeline

Every generated report/export follows one explicit lifecycle, using managed-process runs for queued execution, progress, structured logs, retry/cancel visibility, and Admin operations where execution is not safely synchronous:

1. authorize user, active team, module, dataset, filters, and columns;
2. persist an immutable request snapshot and release/rule version;
3. deduplicate only when the complete authorization and request fingerprint match;
4. select synchronous execution only below explicit safety thresholds, otherwise queue;
5. generate through an idempotent job with concurrency limits per user/team/report type;
6. store the artifact privately with checksum, content type, size, creator, expiry, and status;
7. notify the requester on success or failure;
8. authorize every download again;
9. expire and delete artifacts through retention jobs.

Retries must not create duplicate visible artifacts. A failed or partial artifact is never downloadable.
