# Admin module

Canonical current scope of the Admin module and administrative operational interfaces. Security-sensitive Admin mode and impersonation are documented separately.

## Admin Panel

The admin panel is built from the beginning in parallel with the application foundation.

Rules:

- English only;
- own Presentation layer;
- own routes;
- own layout;
- own menu;
- own permissions;
- same use cases and domain rules;
- extra audit and confirmation;
- no hidden superadmin bypass;
- not a generic CRUD incubator.

Entering `/admin...` routes requires authenticated users to confirm their password through the shared Identity confirmation screen.

The regular application shell shows the Admin entry only when the backend-provided `auth.availableAdminRoutes` includes an available Admin route for the current user/team context. This is UI visibility only; Admin route middleware remains the authorization boundary.

Current Admin tables use the shared `DataTable` wrapper. Their first data column is `public_id`, they keep the most important operational columns visible by default, and they expose remaining safe non-secret table columns through the Columns menu. Search, sorting, pagination, column visibility, and column order are backend-validated and synchronized through deterministic English query-string keys. Admin users can save private or active-team-shared table views, set a default view, copy a system/shared view, and delete only editable non-system views. Team-shared saved-view changes are recorded through the Audit module. When an Admin index exposes safe row actions, the same supported mutating actions are also available as selected-row bulk actions for the currently loaded page.

Admin user and team administration include integrated Team access management. Administrators can assign users to teams during user creation, user editing, team creation, or team editing, and can manage the user's team-scoped roles and direct permissions from either side of the workflow. Team access removal is security-sensitive: it requires a reason, audits the change, removes user-specific authorization assignments in that team, and invalidates sessions operating in that team.

Admin manager hierarchy administration is available at `/admin/managers`. It lets authorized administrators filter by team, preview manager-report changes before saving, create team-scoped manager relationships, end relationships without deleting history, update head-manager status, view the current hierarchy tree, and inspect relationship history. Every mutating manager action requires a reason, validates self-management and DAG cycles on the backend, and records security-sensitive Teams audit events.

The Admin audit browser is available at `/admin/audit`. It is read-only, uses the shared `DataTable` wrapper, and exposes audit records for operational and security review.

The Admin rate-limit browser is available at `/admin/rate-limits`. It is read-only for configured thresholds and shows named policy definitions together with aggregated rejection statistics. Administrators may reset one concrete limiter counter by selecting a policy, entering the exact limiter key, and providing a reason. The reset clears only that key, removes its aggregated rejection-statistics row when present, and records a security audit event with action `rate_limit.counter_reset`, policy, limiter key, actor, reason, and correlation ID. Admin cannot edit thresholds, add policies, delete policies, or disable rate limiting.

The Admin application-log browser is available at `/admin/logs`. It exposes curated application log entries from Atlas' canonical application log source only, parses structured JSON production records and readable development records, groups multiline stack traces under their originating log entry, redacts sensitive context and obvious sensitive inline text, and presents safe operational fields such as level, message, module, source, event name, correlation ID, request ID, environment, and channel through a dedicated expandable log viewer. The UI does not accept filesystem paths, browse directories, download server files, or execute shell commands.

The Admin queues browser is available at `/admin/queues`. It exposes failed jobs from Atlas' configured failed-job table only, shows queue/connection/job/exception summaries, and provides expandable payload and exception details for authorized operators. Administrators may retry one failed job after confirmation. Mass retry is limited to selected known failed-job UUIDs and requires typed confirmation `RETRY`. Retry actions are audited as security-sensitive queue operations. Admin does not expose arbitrary `queue:retry all`, range retry, queue clearing, failed-job flushing, shell access, or arbitrary command execution.

Failed-job retry actions are ModuleGate-checked against the module inferred from the queued job class before retrying.

Laravel Pulse is available from the Admin navigation at `/admin/pulse`. It is a package-owned internal performance dashboard for authorized operational administrators and is protected by `auth`, password confirmation, Pulse's `viewPulse` gate, and the `admin.pulse.view` permission. Pulse is not an Inertia screen and uses its own Livewire/Blade dashboard.

Phase 8 verifies the current Admin UI/table foundation after Phase 7. Phase 9 completes shared UI primitives and Phase 10 completes the shared table/saved-view contract before additional Admin areas rely on broader table behavior.

Initial areas:

- Users
- Roles
- Permissions
- Teams
- Managers
- Logs
- Pulse
- Storage
- System Status
- Queues
- Failed Jobs
- Imports
- Integrations
- Feature Flags
- Audit
- Rate limits
- Module activation

Module activation administration is available at `/admin/modules`. It lists deployed modules, technical availability, global state, active-team effective state, dependencies, and activation support. A module detail screen lets administrators manage global activation where supported, attach or override teams for the module, schedule future changes, cancel pending schedules, and review recent activation history. Team creation and editing also expose module override management so administrators can work from either the module context or the team context.

System Status includes:

- release version, release ID, environment, and optional last-deploy metadata;
- readiness with blocking versus degraded dependency counts and per-check diagnostics;
- PostgreSQL;
- Redis;
- Meilisearch;
- queues;
- scheduler heartbeat freshness;
- failed module activation schedule diagnostics;
- storage;
- last deploy;
- application version.

Failed jobs support safe retry and strong mass-action confirmation.

Audit browser supports filtering by actor, actual actor, impersonated user, entity, action, target type, team, module, source, result, correlation ID, and security flag. Audit browser saved views include active audit filters.

Logs and storage browsing must be secure and must not allow arbitrary server manipulation.

---
