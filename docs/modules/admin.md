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

Admin request-result flashes use the shared keyed `flash.messages` contract consumed by the toast viewport. Operational actions must send localized PL/EN translation keys instead of backend-rendered English sentences or raw exception messages. Technical diagnostics remain available inside operational tables, detail screens, logs, audit records, and process logs.

Phase 24a maintains the current Admin export integration inventory in [Core export foundation and Admin data integration](../roadmap/phase-24a-core-export-foundation.md). Exportable Admin DataTable and custom data surfaces must register Core Exports providers; intentionally unsupported surfaces must keep their rationale documented there.

Custom Admin operational views that do not use the shared `DataTable` filter surface keep their filters in the shared `FilterPanel` pattern: the panel title is `Filters`, Clear is a neutral action, Apply is the primary action, and optional loaded-result summaries sit below the fields. Page-level Create and Back links use the shared `ActionLink`, and form footers use `FormActions`. Inline operational forms keep one- or two-step actions aligned with their fields at desktop widths and wrap predictably on small screens.

Admin user and team administration include integrated Team access management. Administrators can assign users to teams during user creation, user editing, team creation, or team editing, and can manage the user's team-scoped roles and direct permissions from either side of the workflow. Team access removal is security-sensitive: it requires a reason, audits the change, removes user-specific authorization assignments in that team, and invalidates sessions operating in that team.

Admin user administration manages account sensitivity (`normal`, `sensitive`, `technical`, `service`, `integration`) independently from roles and team assignments. The user list exposes the classification, the edit form updates it, and the impersonation start action enforces it.

Admin manager hierarchy administration is available at `/admin/managers`. It lets authorized administrators filter by team, preview manager-report changes before saving, create team-scoped manager relationships, end relationships without deleting history, update head-manager status, view the current hierarchy tree, and inspect relationship history. Every mutating manager action requires a reason, validates self-management and DAG cycles on the backend, and records security-sensitive Teams audit events.

The Admin audit browser is available at `/admin/audit`. It is read-only, uses the shared `DataTable` wrapper, and exposes audit records for operational and security review.

The Admin rate-limit browser is available at `/admin/rate-limits`. It is read-only for configured thresholds and shows named policy definitions together with aggregated rejection statistics. Administrators may reset one concrete limiter counter by selecting a policy, entering the exact limiter key, and providing a reason. The reset clears only that key, removes its aggregated rejection-statistics row when present, and records a security audit event with action `rate_limit.counter_reset`, policy, limiter key, actor, reason, and correlation ID. Admin cannot edit thresholds, add policies, delete policies, or disable rate limiting.

The Admin application-log browser is available at `/admin/logs`. It exposes curated application log entries from Atlas' canonical application log source only, parses structured JSON production records and readable development records, groups multiline stack traces under their originating log entry, redacts sensitive context and obvious sensitive inline text, and presents safe operational fields such as level, message, module, source, event name, correlation ID, request ID, environment, and channel through a dedicated expandable log viewer. The UI does not accept filesystem paths, browse directories, download server files, or execute shell commands.

The Admin queues browser is available at `/admin/queues`. It exposes a queue-operations snapshot made from Atlas configuration and durable failed-job persistence: configured queue names, active queue connection/driver, Horizon path when configured, failed-job counts per known queue, and the retryable failed-job dataset from Atlas' configured failed-job table. It does not duplicate Horizon internals or treat raw Redis queue structures as durable Atlas history. Completed/running operational work that Atlas owns is tracked through Managed Processes, process logs, and owning module state.

The failed-job section shows queue/connection/job/exception summaries and expandable payload and exception details for authorized operators. Administrators may retry one failed job after confirmation. Mass retry is limited to selected known failed-job UUIDs and requires typed confirmation `RETRY`. Retry actions are audited as security-sensitive queue operations. Admin does not expose arbitrary `queue:retry all`, range retry, queue clearing, failed-job flushing, shell access, or arbitrary command execution.

Failed-job retry actions are ModuleGate-checked against the module inferred from the queued job class before retrying.

The Admin managed-process area is split into four operational tabs:

- `/admin/managed-processes` for process run history, start/finish timing, status/source/module filters, and links into the structured process log manager;
- `/admin/managed-processes/imports` for import executions, idempotency state, source type, statistics, and links into the same run log manager;
- `/admin/managed-processes/definitions` for registered definitions and manual registered-process starts;
- `/admin/managed-processes/schedules` for validated five-field cron schedule creation, schedule filters, and schedule disable actions;
- `/admin/search` for Search readiness, registered index descriptors, recent rebuild runs, and confirmed rebuild starts linked to managed-process run details.

The managed-process Admin area does not expose arbitrary shell cron, raw Artisan command execution, filesystem browsing, or unregistered process execution. The run detail screen supports filtering by severity, stage, event type, and safe text context.

The Admin integrations browser is available at `/admin/integrations`. It shows registered adapter status, source-of-truth notes, last success, last error, circuit state, recent synchronization runs, and external API boundary status. Test-connection actions are permission-protected and never display secrets.

The Admin feature flag browser is available at `/admin/feature-flags`. It shows code-registered typed rollout flags, selected-team effective state, global and team values, source precedence, and recent history. Administrators may update global values, update selected-team overrides, and clear selected-team overrides after providing a reason. Feature flags do not grant permissions, activate modules, bypass ModuleGate, or replace backend authorization checks.

Laravel Pulse is available from the Admin navigation at `/admin/pulse`. It is a package-owned internal performance dashboard for authorized operational administrators and is protected by `auth`, password confirmation, Pulse's `viewPulse` gate, and the `admin.pulse.view` permission. Pulse is not an Inertia screen and uses its own Livewire/Blade dashboard.

Laravel Telescope is available from Admin navigation only in local/development environments at `/telescope`. It is a package-owned diagnostics dashboard protected by `auth`, password confirmation, Telescope's `viewTelescope` gate, the active team context, and the `admin.telescope.view` permission. Telescope is not an Inertia screen and remains unavailable in tests, production, and untrusted environments.

Phase 8 verifies the current Admin UI/table foundation after Phase 7. Phase 9 completes shared UI primitives and Phase 10 completes the shared table/saved-view contract before additional Admin areas rely on broader table behavior.

Initial areas:

- Users
- Roles
- Permissions
- Teams
- Managers
- Logs
- Pulse
- Telescope
- Storage
- System Status
- Queues
- Failed Jobs
- Managed processes
- Imports
- Integrations
- Feature Flags
- Audit
- Rate limits
- Module activation

Module activation administration is available at `/admin/modules`. It lists deployed modules, technical availability, global state, active-team effective state, dependencies, and activation support. A module detail screen lets administrators manage global activation where supported, attach or override teams for the module, schedule future changes, cancel pending schedules, and review recent activation history. Team creation and editing also expose module override management so administrators can work from either the module context or the team context.

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

Audit browser supports filtering by actor, actual actor, impersonated user, entity, action, target type, team, module, source, result, correlation ID, impersonation session ID, and security flag. Audit browser saved views include active audit filters. Impersonation session details are available at `/admin/audit/impersonation/{session}` and show start/end, reason, administrator, impersonated user, team, operation count, rejected count, and session events. Admin security history is available at `/admin/audit/security-history` and shows recent security events for all users, with a user selector that matches actor, actual actor, impersonated user, or target context; impersonation does not send real-time user notifications by default. Core Exports powers CSV, XLSX, PDF, and browser print actions for shared Admin DataTables plus page-local operational lists including Files, Integrations, Search, Feature Flags, Managed Processes, Imports, Application logs, Failed jobs, Manager relationship history, impersonation session events, import row errors, and module detail tables.

Logs and storage browsing must be secure and must not allow arbitrary server manipulation.

---
