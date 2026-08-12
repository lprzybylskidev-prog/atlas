# Tables, reports, exports, charts, and print

Canonical shared contract for data tables, query strings, saved views, exports, report headers, charts, report generation, browser print, and PDF rendering.

## Phase 29 DataTable closure state

The shared DataTable, action, filter, state, status, safe-column, effective-locale, and saved-view contracts are implemented. Phase 29 decomposed the central host into focused state units after the Phase 28 size-only acceptance evidence proved insufficient. Saved-view mutations use the shell-neutral `/table-views` routes and `RegisteredTables` validates each table against its owning surface permission and ModuleGate state. Notifications, route-backed regular-user TimeTracking sections, and manager operation tables opt in alongside eligible Admin tables. Reports/Core Exports ownership, existing-view migration, and Chromium/PDF runtime parity are closed and covered by the foundation gate.

Target state: normal tabular data uses the shared DataTable contract; saved views are explicitly enabled per surface; exports/print/PDF values use the effective locale; Reports and Core Exports have explicit ownership and dependency classification; and PDF runtime is smoke-tested before Phase 30.

Tracked issue IDs: `P28-TABLE-001` through `P28-TABLE-009`, `P28-LOC-001`, `P28-ARCH-002`, `P28-ARCH-003`, `P28-RUNTIME-009`, `P28-MODAUD-010`, `P28-MODAUD-017`.

## Tables, Reports, Exports, Charts, and Print

Every table uses the shared TanStack Table wrapper.

The Phase 10 shared `DataTable` wrapper is the only application table framework. Current Admin tables use backend-validated table state, server-side pagination, sorting, and filtering, deterministic English query-string keys, column visibility/order state, row selection for the currently loaded page, loading/empty/error/no-results states, and row actions. Export actions are provided through the Core Exports lifecycle instead of browser-local table generation.

Tables keep readable minimum widths for data cells and row actions. When the visible column set is wider than the available viewport, the shared wrapper uses horizontal scrolling instead of compressing columns until values or actions overlap. Truncated data cells expose the full formatted value through the shared tooltip pattern while keeping the formatted value selectable for normal browser copy operations.

Row actions use the canonical typed `AtlasAction` contract and shared modal flow, including row-specific operation copy when needed. Pages must not implement separate row-action confirmation dialogs, native browser confirmations, or local action icon/button styling for normal tabular rows.

The DataTable host is a composition-only Vue surface. `useDataTableController` assembles TanStack integration and lifecycle wiring while focused units own query/applied-state serialization, sorting/pagination adaptation, column visibility/order, current-page selection, local persistence, saved-view payloads, row/bulk action execution, and locale-aware formatting. State-row and pagination rendering remain focused shared components. The public DataTable props and `bulkAction` event remain the canonical consumer API.

This boundary is permanent executable architecture, not a line-count convention. A source guard rejects known responsibility implementations when they return to `DataTable.vue`, mutation fixtures exercise every guarded responsibility family, and direct Vitest coverage protects the extracted pure units. New table behavior belongs in the corresponding focused unit or another explicitly named table unit; do not re-centralize it in the host.

Normal tabular datasets use this composition. A specialized timeline or code/log reader may use a different responsive presentation, but it must reuse shared states and actions and document why a normal table is not appropriate.

Saved views are explicitly enabled per eligible table, independent of shell mode. The shared component uses the shell-neutral `/table-views` route contract and never hardcodes `/admin`. Table definitions and their saved-view access contracts are registered through the typed shared `RegisteredTables` registry. Eligible regular-user and manager tables—including Notifications and accepted TimeTracking reports/operations—receive the same safe capability without Admin mode; Admin registrations still require an active Admin-mode session. Manager operation tables use manager-specific keys rather than reusing Admin keys, so their permissions and persisted state cannot cross shell boundaries. A table may disable persistence only when its registered contract documents why saved state has no product value.

Saved views persist safe table configuration only: search/filter state, sorting, visible columns, column order, grouping keys, and fixed or dynamic time-range metadata. They never persist row data. Private views are owner-scoped, team-shared views are active-team scoped and permission-gated, and system views are read-only from normal table UI. System views may be copied into private or team-shared views. Defaults are user-and-table scoped. Every read and mutation revalidates table registration, current authorization, active team, ModuleGate state, allowed filters/columns, and view visibility; shared/system changes are audited.

Phase 24 implements the later report/export/PDF/chart/print artifact lifecycle after files, notifications, audit, active-team context, and operational visibility exist. Phase 24a moves the reusable lifecycle into the Core Exports module so Admin and business data surfaces can export without depending on optional Reports.

The first user-facing column is the human name or business value. A public identifier may lead only on a justified technical Admin surface. Each column is explicitly allowed or forbidden and visible or hidden; forbidden columns never enter the column menu or client state. Regular-user and manager tables forbid internal IDs, non-user-facing public identifiers, raw enums, deep links, session identifiers, technical event names, database values, and precise diagnostics. Secret values such as passwords, remember tokens, authentication tokens, MFA secrets, and recovery codes are never exposed as table columns.

`FilterPanel` owns responsive filter layout, canonical Apply/Clear labels, active-filter chips, clear-one, clear-all, and result/no-results context. The shared UI state contract covers initial and refresh loading, empty dataset, no results, recoverable and fatal errors, permission denied, module unavailable, offline, and stale data. The status catalog owns stable keys, PL/EN labels, meaning, tone, icon, accessibility text, and allowed surfaces; unknown tokens are shown only as explicitly marked diagnostics, never humanized into plausible copy.

Metric tiles that summarize a table, report, chart, timeline, or another detailed dataset use the same authorized data scope as that detail surface. They aggregate the complete dataset after the currently applied report filters and DataTable search, including rows beyond the current page. Sorting, page number, and page size never change aggregate values. A page may also show a contextual metric for a distinct visible dataset or for the inspected record itself, but that ownership must be explicit; it must not silently query a broader global scope. Array-backed tables expose their complete searched dataset through `TableResult::filteredRows`, while database-backed tables apply the same filter and search predicates to a separate aggregate query. Tests cover search/filter coupling and pagination independence at the shared contract and representative HTTP surfaces.

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
- the effective locale captured in the immutable request snapshot.

Admin tables using the shared backend `TableState` enter the export lifecycle through `App\Shared\Application\Exports\Contracts\AdminDataTableExportProvider` implementations. Core Exports consumes those shared contributions through its provider registry, then revalidates the table state against the table definition and provider-authorized columns before recording the immutable export request.

Small exports may run synchronously through Core Exports.

Large exports use managed-process queues and notify the user when ready. This depends on the managed-process, notification, and operational-health foundations rather than inventing a local progress mechanism. Admin export requests emit one localized immediate keyed flash for the request outcome; terminal artifact readiness, failure, or completion belongs to the export lifecycle notification path with a useful deep link when an artifact or result view exists.

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

Use shared Atlas chart wrappers owned by the repository.

Charts:

- render through shared Atlas chart wrappers in application UI;
- render through the shared report HTML/SVG chart contract for PDF and browser print;
- use the same filters as the table;
- supplement, not replace, tabular data;
- may be included in PDF and print;
- aggregate large ranges by day, week, or month;
- must have an actual analytical purpose.

---

### Report generation pipeline

Every generated report/export follows one explicit Core Exports lifecycle, using managed-process runs for queued execution, progress, structured logs, retry/cancel visibility, and Admin operations where execution is not safely synchronous:

1. authorize user, active team, module, dataset, filters, and columns;
2. persist an immutable request snapshot and release/rule version;
3. deduplicate only when the complete authorization and request fingerprint match;
4. select synchronous execution only below explicit safety thresholds, otherwise queue;
5. generate through an idempotent job with concurrency limits per user/team/report type;
6. store the artifact privately with checksum, content type, size, creator, expiry, and status;
7. notify the requester once on success or failure with localized notification text and a download deep link when an artifact exists;
8. authorize every download again;
9. expire and delete artifacts through retention jobs.

Retries must not create duplicate visible artifacts. A failed or partial artifact is never downloadable.
