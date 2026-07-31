# Admin module

Canonical current scope of the Admin module and administrative operational interfaces. Security-sensitive Admin mode and impersonation are documented separately.

## Admin Panel

The admin panel is built from the beginning in parallel with the application foundation.

Rules:

- Polish and English localization through the shared localization model;
- own Presentation layer;
- own routes;
- own layout;
- own menu;
- own permissions;
- same use cases and domain rules;
- extra audit and confirmation;
- no hidden superadmin bypass;
- not a generic CRUD incubator.

Entering `/admin...` routes requires authenticated users to enter explicit administrative mode through the shared Laravel/Fortify-style `/user/confirm-password` reauthentication screen. Administrative mode has inactivity and absolute lifetime limits. High-risk Admin operations additionally require a separate fresh confirmation through the same screen. Atlas classifies hard delete, irreversible anonymization, MFA reset, administrator permission changes, sensitive-account impersonation override, and closed-period TimeTracking corrections as high-risk operation types.

The regular application shell shows the Admin entry only when the backend-provided `auth.availableAdminRoutes` includes an available Admin route for the current user/team context. This is UI visibility only; Admin route middleware remains the authorization boundary.

The Admin shell workspace navigation exposes separate links to the regular application dashboard at `/` and the Admin dashboard at `/admin`. The Admin dashboard is the operational entry point for release identity, detailed readiness checks, and deployed module health.

The Admin dashboard is an operational status surface, not a duplicate navigation directory. It is intentionally organized around three primary cards: Release, Readiness, and Modules. New Admin operational areas must contribute a meaningful signal to the Modules card when they expose health, queues, failures, approvals, security events, module state, integrations, files, managed processes, imports, reports, or operator action.

Current Admin tables use the shared `DataTable` wrapper. Their first data column is `public_id`, they keep the most important operational columns visible by default, and they expose remaining safe non-secret table columns through the Columns menu. Search, sorting, pagination, column visibility, and column order are backend-validated and synchronized through deterministic English query-string keys. Admin users can save private or active-team-shared table views, set a default view, copy a system/shared view, and delete only editable non-system views. Team-shared saved-view changes are recorded through the Audit module. When an Admin index exposes safe row actions, the same supported mutating actions are also available as selected-row bulk actions for the currently loaded page.

Admin request-result flashes use the shared keyed `flash.messages` contract consumed by the toast viewport. One user action must emit at most one immediate flash/toast. Operational actions must send localized PL/EN translation keys instead of backend-rendered English sentences or raw exception messages. Technical diagnostics remain available inside operational tables, detail screens, logs, audit records, and process logs.

Phase 24a maintains the current Admin export integration inventory in [Core export foundation and Admin data integration](../roadmap/phase-24a-core-export-foundation.md). Exportable Admin DataTable and custom data surfaces must register Core Exports providers; intentionally unsupported surfaces must keep their rationale documented there.

Custom Admin operational views that do not use the shared `DataTable` filter surface keep their filters in the shared `FilterPanel` pattern: the panel title is `Filters`, Clear is a neutral action, Apply is the primary action, and optional loaded-result summaries sit below the fields. Page-level Create and Back links use the shared `ActionLink`, and form footers use `FormActions`. Inline operational forms keep one- or two-step actions aligned with their fields at desktop widths and wrap predictably on small screens.

Admin user and team administration include integrated Team access management. Administrators can assign users to teams during user creation, user editing, team creation, or team editing, and can manage the user's team-scoped roles and direct permissions from either side of the workflow. Team access removal is security-sensitive: it requires a reason, audits the change, removes user-specific authorization assignments in that team, and invalidates sessions operating in that team.

Admin user administration manages account sensitivity (`normal`, `sensitive`, `technical`, `service`, `integration`) independently from roles and team assignments. The user list exposes the classification, the edit form updates it, and the impersonation start action enforces it.

Admin manager hierarchy administration starts at `/admin/managers`. The index is a team-filtered DataTable of users who are managers in the selected team, including regular managers and head managers, and links to `/admin/managers/create?team={team}` for adding a new manager relationship. The index supports operational filters for manager type, direct-report presence, and subtree-report presence. Manager create and detail pages use the same control layout: they show the hierarchy below the selected manager, allow selecting multiple new direct reports with one effective date and reason, preview the impact for each selected report before saving, show active direct reports with relationship start date and creation reason, end existing direct-report relationships from the direct-report row, and update that user's head-manager status. Every mutating manager action requires a reason, validates self-management and DAG cycles on the backend, and records security-sensitive Teams audit events.

The Admin audit browser is available at `/admin/audit`. It is read-only, uses the shared `DataTable` wrapper, shows operational metrics, supports saved views and exports, and exposes backend-applied filters for actor, actual actor, impersonated user, impersonation session, target, target type, action, team, module, source, correlation ID, result, security flag, and date range. Rows with an impersonation session link to `/admin/audit/impersonation/{session}`, which shows the session summary and session-scoped audit events. Security history is available as the Audit subview at `/admin/audit/security-history`, with user, action, result, source, and date filters across actor, actual actor, impersonated user, and target context. Audit and security history are diagnostic evidence surfaces only; administrators do not acknowledge, resolve, edit, delete, or otherwise mutate audit records from these views.

The Admin rate-limit browser is available at `/admin/rate-limits`. It is read-only for configured thresholds and shows named policy definitions together with aggregated rejection statistics, metrics, backend-applied filters, saved views, and exports. Administrators may reset one concrete limiter counter by selecting a policy, entering the exact limiter key, and providing a reason. The reset clears only that key, removes its aggregated rejection-statistics row when present, and records a security audit event with action `rate_limit.counter_reset`, policy, limiter key, actor, reason, and correlation ID. Admin cannot edit thresholds, add policies, delete policies, or disable rate limiting.

The Admin application-log browser is available at `/admin/logs`. It exposes curated entries from readable `*.log` files discovered in `storage/logs`, defaults to `laravel.log` when it exists, parses structured JSON production records and readable development records, groups multiline stack traces under their originating log entry, redacts sensitive context and obvious sensitive inline text, and presents safe operational fields such as level, message, module, source, event name, correlation ID, request ID, environment, and channel through a dedicated log viewer rather than a primary DataTable. The view has backend-applied log-file, level, module, source, date, and search filters, summary metrics, export support for the filtered log projection, and uses the shared `CodeViewer` with line numbers, wrapping controls, copy support, and log/error highlighting for payload detail. The UI accepts only discovered log filenames and does not accept filesystem paths, browse directories, download server files, or execute shell commands.

The Admin queues browser is available at `/admin/queues`. It exposes queue names with failed-job counts, durable failed jobs requiring attention, handled failed jobs, and expandable payload/exception detail viewers for the currently loaded rows. Failed jobs use the shared Admin DataTable contract with backend-applied connection, queue, handling-status, and failed-date filters, saved views, exports, stable retry row actions, and bulk retry. Handled failed jobs are hidden by default and can be shown through the handling-status filter.

The failed-job section shows queue/connection/job/exception summaries and expandable payload and exception details for authorized operators. Administrators may retry one failed job after confirmation, retry selected rows through a DataTable bulk action, mark one failed job as handled, or mark selected rows as handled. Handled failed jobs stop contributing to Admin failed-job dashboard warnings and operational alert thresholds while remaining visible in diagnostics when the handled filter is selected. Retry and handling actions are audited as security-sensitive queue operations. Admin does not expose arbitrary `queue:retry all`, range retry, queue clearing, failed-job flushing, shell access, or arbitrary command execution.

Failed-job retry actions are ModuleGate-checked against the module inferred from the queued job class before retrying.

The Admin files browser is available at `/admin/files`. It exposes private file metadata, scan state, size, checksum, latest scan evidence, quarantine/availability timestamps, blocked or infected visibility, row/bulk mark-as-handled actions for non-clean scan states, and a confirmed row action to request a malware rescan. Files use the shared Admin DataTable contract with backend-applied scan-state, extension, provider, availability, handling-status, and created-date filters, saved views, pagination, and exports. Handled problematic files are hidden by default, remain visible through the handling filter, and stop contributing to Admin file-blocker dashboard signals until a rescan reopens the review cycle. The screen does not expose arbitrary server filesystem browsing, raw storage paths, physical file manipulation, or a manual clean override.

The Admin managed-process area is split into three operational subviews:

- `/admin/managed-processes` for combined ordinary and import-linked process run history, start/finish timing, status/source/module/import/idempotency/handling filters, row and bulk handling actions for failed, warning, cancelled, or expired runs, and links into the structured process log manager;
- `/admin/managed-processes/definitions` for registered definitions and modal manual start actions;
- `/admin/managed-processes/schedules` for schedule filters and disable actions, with `/admin/managed-processes/schedules/create` owning schedule creation;
- `/admin/search` for Meilisearch readiness, registered Search index descriptors with filters/export, recent rebuild runs, and confirmed rebuild starts linked to managed-process run details.

The managed-process Admin area does not expose arbitrary shell cron, raw Artisan command execution, filesystem browsing, or unregistered process execution. The run detail screen supports filtering by severity, stage, event type, and safe text context.

The Admin integrations browser is available at `/admin/integrations`. It shows registered adapter status, source-of-truth notes, last success, last error, circuit state, recent synchronization runs, and external API boundary status. Adapters use shared DataTable filters and exports. Test-connection actions are permission-protected and never display secrets.

The Admin feature flag browser is available at `/admin/feature-flags`. It shows code-registered typed rollout flags, selected-team effective state, global and team values, source precedence, owner/lifecycle filters, saved views, exports, and recent history. Administrators may update global values, update selected-team overrides, and clear selected-team overrides after providing a reason. Feature flags do not grant permissions, activate modules, bypass ModuleGate, or replace backend authorization checks.

Laravel Pulse is available from the Admin navigation at `/admin/pulse`. It is a package-owned internal performance dashboard for authorized operational administrators and is protected by `auth`, password confirmation, Pulse's `viewPulse` gate, and the `admin.pulse.view` permission. Pulse is not an Inertia screen and uses its own Livewire/Blade dashboard.

Laravel Telescope is available from Admin navigation only in local/development environments at `/telescope`. It is a package-owned diagnostics dashboard protected by `auth`, password confirmation, Telescope's `viewTelescope` gate, the active team context, and the `admin.telescope.view` permission. Telescope is not an Inertia screen and remains unavailable in tests, production, and untrusted environments.

Phase 25 rebuilt the Admin presentation around the shared Atlas view standard. Current primary Admin areas are Dashboard/System Status, Users, Teams, Managers, Roles, Permissions, Szablony, Modules, Queues, Managed Processes, Files, Search, Integrations, Feature Flags, Rate Limits, Application Logs, Audit bezpieczeństwa, and package-owned Pulse/Telescope diagnostics links. Imports do not have a standalone Admin route; import execution visibility belongs to Managed Processes, where import-linked runs keep their context and detail links.

Module activation administration is available at `/admin/modules`. The index is a shared DataTable for deployed modules with filters for category, technical availability, global state, active-team state, effective activation, configuration source, activation support, and pending schedules. A module detail screen at `/admin/modules/{module}` shows the module as a whole: technical state, global activation, global scheduling, dependencies, team-state table, recent history, and schedules. Team-specific module controls live in the separate `/admin/modules/{module}/teams/create` screen, where administrators select one team, create or update that team's override, schedule team-specific changes, clear an existing override, and inspect that team's history and schedules. Non-editable modules, such as Core read-only modules, do not show activation, scheduling, clearing, history, or schedule-management forms. Team creation and editing also expose module override management so administrators can work from either the module context or the team context.

Application-category modules are not automatically enabled by system bootstrap. They can therefore be deployed in code while waiting for explicit administrative acceptance through Module activation administration.

System Status includes:

- release version, release ID, environment, Laravel version, PHP version, runtime, timezone, and optional last-deploy metadata;
- readiness with blocking versus degraded dependency counts and per-check diagnostics;
- PostgreSQL;
- Redis;
- Meilisearch;
- scheduler heartbeat freshness;
- deployed modules with technical availability, global/team/effective activation, dependencies, and module-owned issues such as queue failures, rate-limit rejections, file scan blockers, integration circuit breakers, failed synchronization runs, and failed module activation schedules;
- managed-process and import signals for active runs, failures, warning completions, schedules, and import row warnings/errors;
- storage;
- last deploy;
- application version.

Failed jobs support safe retry and strong mass-action confirmation.

Audit browser supports filtering by actor, actual actor, impersonated user, entity, action, target type, team, module, source, result, correlation ID, impersonation session ID, and security flag. Audit browser saved views include active audit filters. Impersonation session details are available at `/admin/audit/impersonation/{session}` and show start/end, reason, administrator, impersonated user, team, operation count, rejected count, and session events. Admin security history is available at `/admin/audit/security-history` and shows recent security events for all users, with a user selector that matches actor, actual actor, impersonated user, or target context; impersonation does not send real-time user notifications by default. Core Exports powers CSV, XLSX, PDF, and browser print actions for shared Admin DataTables plus page-local operational lists including Files, Integrations, Search, Feature Flags, Managed Processes including import-linked runs, Application logs, Failed jobs, Manager relationship history, impersonation session events, import row errors, and module detail tables.

Logs and storage browsing must be secure and must not allow arbitrary server manipulation.

---
