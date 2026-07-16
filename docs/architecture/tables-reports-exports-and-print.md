# Tables, reports, exports, charts, and print

Canonical shared contract for data tables, query strings, saved views, exports, report headers, charts, report generation, browser print, and PDF rendering.

## Tables, Reports, Exports, Charts, and Print

Every table uses the shared TanStack Table wrapper.

The current Phase 7 foundation provides the first shared TanStack `DataTable` wrapper, initially used by operational Admin tables and reusable by later application tables. It includes local search, sorting, pagination, column visibility, row selection, empty state, row actions, and CSV export for the currently loaded dataset. Persisted table instances store search, sorting, pagination, and column visibility under a stable table key; row selection is intentionally not persisted. The full server-side reporting/export lifecycle below remains the target for larger datasets and later business reports.

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

Backend validates all requested filters, columns, and sorting.

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
- may be copied;
- may be managed centrally where justified.

Shared-view changes are audited.

### Exports

Support from the beginning:

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

Large exports use queues and notify the user when ready.

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

Every generated report/export follows one explicit lifecycle:

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
