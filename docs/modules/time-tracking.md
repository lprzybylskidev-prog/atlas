# TimeTracking module

Canonical complete behavior of the optional TimeTracking module. Read this only for work touching time registration, breaks, other work, inactivity, corrections, settlement, reports, or impersonation simulation.

## Current implementation status

Phase 27 and Phase 27a are complete history. Phase 28 consolidated the module onto one route-backed user/manager/Admin delivery model and closed the remaining ownership, UI, audit, and transaction gaps.

The current implementation provides the optional `time_tracking` module manifest, exact interval allocation primitives, user-team tracking enablement, official work-session persistence, module-context segments, break/Other work locks, policies and review states, maintenance handling, inactivity/offline reconciliation, correction and settlement flows, ModuleGate enforcement, public analytical contracts, route-backed user reporting, parity manager/Admin operations, Admin exports, report charts, period comparison, impersonation-safe simulation, development demo data, and deactivation guard integration.

## TimeTracking Optional Module

`TimeTracking` is an optional foundation module and must work independently.

A project may enable:

- no time tracking;
- standalone time tracking.

The module tracks:

- active work;
- module context;
- breaks;
- other work outside the computer;
- maintenance;
- inactivity;
- corrections;
- manual entries;
- manager approval;
- reports.

TimeTracking must remain self-contained and must not depend on a future HR, payroll, leave, or personnel module.

Any future integration with external calendars or workforce systems must be added later through explicit public contracts and a separate accepted roadmap decision.

### Analytical boundaries

TimeTracking is an operational data source for work-time evidence, reporting, corrections, and later explicit analytics. It is not an automatic employee-rating engine.

Atlas does not calculate a default productivity score, performance grade, one-number ranking, good/bad employee classification, bonus, sanction, payroll, HR, disciplinary, or employment decision from TimeTracking data.

Mouse, click, keyboard, scroll, touch, activity, screen-time, and presence signals may support inactivity and traceability workflows, but they cannot determine productivity on their own.

Future analytics may use TimeTracking data only through explicit, versioned, attributable metric definitions owned by a module or analytics policy. Derived analytical results must remain traceable to source events and the applied rule version.

The public analytical API exposes three contract families:

- `BusinessEventRecorder` accepts module-owned business events with stable event keys, schema versions, source event IDs, metric inputs, and snapshots of team, role, process, module, and work-session context;
- `MetricDefinitionProvider` lets modules contribute versioned metric definitions and calculation rule keys without central TimeTracking ownership of business meaning;
- `MetricRecalculator` recalculates a selected metric and rule version for a bounded time range when the required source events still exist.

Derived metric results must carry source event identifiers and the rule version used to produce them.

### User-team activation

Time tracking is enabled per user-team assignment.

The same user may be tracked in one team and untracked in another.

Persistence lives in the `optional_time_tracking` PostgreSQL schema. `user_team_settings` stores one row per `core_teams.team_user_assignments` record with a `tracking_enabled` flag. Missing settings mean tracking is disabled. Ended team assignments are not treated as trackable even if a historical settings row remains enabled.

Users whose active user-team assignment is not enabled for TimeTracking are not counted as working users for that team. Even if they hold administrative or reporting permissions, they do not see the regular `Work time` user-report link. TimeTracking currently does not expose regular-user email-notification preference types in the user profile panel.

The module supports both global and team-level activation. Deactivation is guarded: Atlas must block disabling TimeTracking when matching active work, active break, active Other work, active maintenance, pending corrections, or unsafe reporting jobs exist. The current guard is registered through the shared module deactivation registry and reads through the `TimeTrackingDeactivationReadiness` port; persistence-backed readiness is added with the real TimeTracking tables.

TimeTracking routes, jobs, reports, and live-status channels must use the `TimeTrackingModuleAccess` application guard before doing module work. The guard always checks the canonical `time_tracking` module through the shared `ModuleGate` with the active team, active user, and required permission supplied by the delivery layer. Route permissions using the `time-tracking.*` prefix resolve to the same `time_tracking` module key. The work-session synchronizer also uses this guard; if TimeTracking becomes unavailable for the active context, any open work session is closed with the `module_unavailable` reason instead of continuing to count official time.

### Work start and end

- work starts automatically after login into a tracked team;
- logout ends work;
- there is no separate normal end-work button;
- tracked-to-untracked team switch ends the old segment;
- untracked-to-tracked starts a new segment;
- tracked-to-tracked ends the old and starts a new one;
- switching is blocked during break or other work.

Current implementation owns `optional_time_tracking.work_sessions`, referenced in code through the module-owned `TimeTrackingDatabaseTable`. A work session stores user, team, Laravel session ID, exact `started_at`/`ended_at` instants, exact elapsed seconds at closure, and the technical closure reason. A partial unique index allows only one open official work session per user. The TimeTracking web middleware synchronizes work after authenticated requests with an active team and closes open work before logout.

TimeTracking contributes shared Inertia activity settings and work-time route candidates through explicitly registered Presentation contributors. The global Inertia middleware does not import TimeTracking internals or query TimeTracking tables directly.

Multiple browser tabs sharing the same Laravel session synchronize into the same active work session and move only the active module-context segment. If another browser session starts tracking the same user and team, the previous open work session is closed with `session_superseded` and the new session becomes authoritative.

### Module activity

Track active module as time segments.

A module change:

- closes the previous segment;
- opens a new segment.

With multiple tabs:

- the tab with latest focus or real user activity is active;
- background tabs do not create parallel activity;
- synchronize one active module across tabs.

General pages count as work under the `System` context until Atlas gains business modules with explicit, user-facing work contexts.

Current implementation stores active module context in `optional_time_tracking.module_context_segments`. Each work session may have only one open context segment. Existing Atlas foundation, Admin, user, and manager routes all record `module_key=system` and `context_key=System`; more granular context values must be introduced only with future business modules that define useful operational labels.

Backend synchronization prevents parallel module-context segments for the same work session. The shared Activity Tracker arbitrates browser focus and real activity across tabs with `BroadcastChannel` plus a `localStorage` fallback, while the backend remains authoritative for the single open segment.

Store raw timestamps to the second.

Work, breaks, and other-work intervals may cross midnight as one logical interval. Persist the real start/end timestamps; split durations by the `Europe/Warsaw` calendar day only for reporting, limits, settlements, and exports.

Do not round individual segments.

Aggregate first, format later.

### Offline and client-clock integrity

The backend is authoritative for official timestamps and state transitions.

Client-side offline/activity duration uses a monotonic clock such as `performance.now()` for elapsed duration and never trusts the user's wall clock for official time.

Offline events include a bounded monotonic duration, sequence identifier, tab/device lease identifier, and server-known anchor. The backend validates ordering, maximum gaps, duplication, session validity, and clock anomalies before accepting or rejecting reconciliation.

Offline reconciliation cannot create parallel work, bypass inactivity rules, extend an expired session, or overwrite a newer server state.

The domain reconciliation decision records whether an offline report was accepted as still active, accepted but ended at the inactivity threshold, or rejected because it was duplicate, reordered, an excessive gap, tied to an expired session, tied to another active device lease, or showed a clock anomaly.

### Breaks

Users may take any number of breaks.

Daily break limit:

- global default;
- team override;
- user-team override;
- resolution order: user-team, team, global.

Break policy persistence lives in `optional_time_tracking.break_policies`. Scope values are `global`, `team`, and `user_team`; `user_team` references the `team_user_assignments` internal identifier through policy ownership rather than a foreign key because scope interpretation is part of TimeTracking policy resolution. Missing rows fall back to the suggested defaults: 15 minutes daily break limit and 4 hours maximum single break duration. Resolution order is user-team, team, global, then default.

Break limit is calculated per user-team and calendar day in `Europe/Warsaw`.

Excess break time is not counted as work.

Configured daily regular-break limits and maximum single-break durations cannot be lower than 1 minute.

Break UI shows remaining time and then exceeded time.

During break:

- whole application is locked;
- any URL redirects to break view;
- backend enforces the lock;
- ending break requires password and MFA when active or required;
- team switching is blocked;
- normal logout is blocked.

Current implementation persists break lock records in `optional_time_tracking.breaks` and exposes the lock through `ActiveTimeLockStore`. `BreakSessionCoordinator` starts a break from the user's active work session, closes it with exact elapsed seconds, supports technical forced closure that requires manager review, and closes expired breaks at the configured maximum single-break duration.

While a break is active, the TimeTracking web middleware redirects authenticated `GET` requests to `users.work-time.break.show` (`/user/work-time/break`) and blocks mutating routes outside the dedicated break-end endpoint with HTTP `423 Locked`. The break screen is rendered by `resources/js/Pages/TimeTracking/BreakLock.vue`, uses the authentication shell, shows elapsed time plus either remaining time or exceeded time, and requires the current password plus MFA when active or required before returning to work through `users.work-time.break.end` (`POST /user/work-time/break/end`). The screen countdown is user-facing orientation only; backend timestamps remain authoritative.

Forced session termination closes the break technically and audits the reason.

Breaks may cross midnight and are split in reporting by calendar day without ending the logical break.

`BreakDailyAllocationCalculator` uses the shared exact calendar-day splitter to allocate one logical break across `Europe/Warsaw` calendar days. It reports each day's break seconds, counted seconds up to that day's policy limit, excess seconds, and remaining seconds.

Maximum single break duration:

- global;
- team;
- user-team;
- default approximately 4 hours;
- technical auto-close after limit;
- session ends;
- item requires manager review;
- user and manager are notified.

The current backend enforcement closes expired breaks at `started_at + maximum_single_break_seconds`, marks them for manager review, and closes the matching work session with `break_maximum_duration`.

Warn before auto-close and show a countdown.

Break policy resolution also carries `warning_before_maximum_seconds`, inherited through the same user-team, team, global, and default order as the break duration limits. The default warning is 15 minutes before the maximum single-break duration, shortened automatically for smaller custom maximums. `BreakSessionCoordinator::recordDueReminders()` records each due reminder once per break in `optional_time_tracking.break_reminders` and records audit evidence for reminder batches and technical closure batches. Break reminder records do not publish user email notifications because a user with an active break is already in the break lock workflow.

### Other work

Users may start `Other work` at any time.

Require:

- description;
- category when configured;
- optional end note.

Categories:

- team-specific;
- typed stable key;
- PL/EN labels;
- optional description;
- optional required comment;
- deactivate instead of delete;
- audited.

Other work locks the normal application UI but lets background async processes continue.

No inactivity logout applies during other work.

Current implementation persists Other work lock records in `optional_time_tracking.other_work`. `OtherWorkSessionCoordinator` starts Other work from the user's active work session, closes it with exact elapsed seconds, supports forced closure, and does not auto-close active Other-work records because work outside Atlas may legitimately run for long periods. Other work categories live in `optional_time_tracking.other_work_categories`; categories are team-scoped only, have stable lowercase keys, PL/EN labels, optional PL/EN descriptions, a `requires_comment` flag, optional auto-approval, and are deactivated instead of deleted. Authorized head managers and administrators manage the category dictionary from their canonical work-time records surfaces.

While an Other work record is active, the TimeTracking web middleware redirects authenticated `GET` requests to `users.work-time.other-work.show` (`/user/work-time/other-work`) and blocks mutating routes outside the dedicated Other-work end endpoint with HTTP `423 Locked`. The lock screen is rendered by `resources/js/Pages/TimeTracking/OtherWorkLock.vue`, uses the authentication shell, shows elapsed time, displays the localized category label, description, and approval state, and lets the user end the record through `users.work-time.other-work.end` (`POST /user/work-time/other-work/end`) with current-password confirmation, MFA when required, and an optional end note. Ending a pending Other-work record preserves manager-review requirements; ending an auto-approved record keeps it accepted unless a later manager challenge moves it to `under_review`.

Other work does not record start reminders, limit warnings, timeout auto-closures, policy rows, or user email notifications. Audit evidence remains recorded for starts, endings, forced closures, and under-review transitions. Any future bounded Other-work variant must introduce a new explicit contract and user-facing copy instead of reusing the regular Other work flow.

Other work ends through the user's return action, forced closure, or maintenance interruption.

Default status requires manager decision.

Selected categories may support automatic approval globally or per team.

Emergency, technical, or timeout closure always requires manual verification.

A manager may move an automatically approved item to `under_review` with reason.

### Maintenance

Maintenance is global for the whole application, not module-specific.

It may be:

- scheduled;
- started immediately as emergency maintenance.

Only tracked users with an active work session at maintenance start receive maintenance time.

Users logged out before maintenance do not.

Maintenance:

- interrupts break;
- technically ends other work;
- counts as work;
- records and publishes maintenance transitions;
- is audited.

After maintenance:

- allow up to 10 minutes for re-login;
- if user returns in time, count the gap;
- otherwise end at maintenance completion.

Current implementation persists global maintenance windows in `optional_time_tracking.maintenance_windows` and impacted active sessions in `optional_time_tracking.maintenance_affected_sessions`. Scheduled windows can be activated explicitly by the internal maintenance coordinator; emergency windows start immediately. At activation, only users with an active work session are snapshotted as impacted. Active breaks are forced closed for manager review, and active Other work records are technically closed as `under_review`. Completing a window sets a 10-minute return deadline for affected sessions; return registration succeeds only inside that deadline, and reporting derives the accepted maintenance gap from that evidence. Scheduling, emergency start, activation, completion, and return registration write TimeTracking audit events and publish the accepted non-sensitive status transition. The coordinator is intentionally an internal application surface for deployment/maintenance orchestration: Atlas does not expose an operator maintenance calendar, planned-warning UI, or a separate post-deadline session-finalizer.

### Inactivity

Use a shared frontend Activity Tracker monitoring real activity such as:

- mouse;
- click;
- keyboard;
- scroll;
- touch.

Throttle reporting.

Backend remains authoritative.

After individual inactivity threshold:

- show modal;
- 30-second countdown;
- any real activity dismisses it;
- no button is required;
- if no activity occurs, counted work ends at warning start and user is logged out.

If the browser closes, backend ends work after inactivity threshold.

Multiple tabs share activity state.

Only one real active working device/session is allowed.

A second-device login warns and asks whether to terminate the previous work session.

Current implementation provides `InactivityCoordinator`, which makes the backend authoritative for inactivity decisions. The default policy starts the warning 5 minutes after the last trusted activity or heartbeat and uses a 30-second warning window. Configured inactivity thresholds cannot be lower than 1 minute. Counted work is closed at the warning start, not at the warning end. Active break or Other work locks prevent inactivity from closing the official work session. Browser-heartbeat loss uses the same backend decision path.

The application shell exposes Activity Tracker configuration only when the authenticated user has an active tracked work session, the active team is known, the route permission is granted, and no active break or Other work lock is open. The frontend tracker listens for pointer, click, keyboard, scroll, touch, and focus activity; throttles noisy events; synchronizes activity across tabs with `BroadcastChannel` and a `localStorage` fallback; and uses monotonic elapsed time instead of the user's wall clock. When the threshold is reached, the browser posts elapsed inactivity to the backend, the backend derives the trusted last-activity timestamp from server time, and the shared application layout renders the localized 30-second warning dialog. Real activity dismisses the warning; if the countdown reaches zero, the browser performs the normal logout flow.

### Offline behavior

Temporary connection loss does not end work immediately.

- show offline status;
- synchronize after reconnect;
- if offline exceeds the inactivity threshold, end work normally;
- do not count indefinitely;
- inform user after reconnect whether session remains active;
- incorrect accounting is handled through a correction request.

Current implementation reuses the Activity Tracker in the shared application layout for temporary offline handling. While the browser reports offline, the layout shows a localized TimeTracking offline banner and pauses inactivity requests. On reconnect, the tracker sends monotonic elapsed offline/idle time to the backend activity endpoint. The backend remains authoritative for the cutoff decision, and the layout shows a localized synchronization result telling the user whether counted work remains active or was ended because the inactivity threshold was exceeded. Failed reconciliation is explicit and asks the user to refresh after the connection stabilizes.

### Managers

Manager hierarchy is team-scoped and shared with the manager system.

Direct managers see direct reports.

Head managers see their subtree.

The manager panel exposes one canonical route-backed records area with summary and bounded time-range filters:

- today;
- week;
- settlement period;
- month;
- year;
- all;
- custom.

Support live worker status via WebSockets:

- working;
- break;
- other work;
- maintenance;
- offline;
- no session;
- current module.

The summary and four detail sections refresh from non-sensitive team status events and use the same operational component, states, actions, and detail contracts as Admin, with manager hierarchy scope applied by the backend.

### Corrections and approvals

Users may:

- request descriptive correction;
- propose exact changes;
- cancel their own pending request with reason.

Managers may:

- reject;
- partially correct;
- create final correction;
- terminate active subordinate session with permission and reason.

First authorized manager decision wins.

Preserve:

- original;
- proposed;
- final;
- actor;
- reason;
- history.

Head manager may create a completely new manual time entry in exceptional cases with separate permission, reason, reauthentication, MFA, audit, and visible manual marker.

Correction requests have no decision deadline and no intermediate ownership step. Authorized administrators and managers may decide pending corrections whenever the record is in their backend-authorized scope.

Current implementation persists correction requests in `optional_time_tracking.correction_requests`, exact proposals in `optional_time_tracking.correction_proposals`, and request history in `optional_time_tracking.correction_history`. Correction requests have canonical nullable `source_type` and `source_id` columns for the concrete source record. Current source types are `work_session`, `break`, and `other_work`; source-backed user requests populate the matching source type and source identifier, while older work-session use cases populate `source_type=work_session` when a work session is supplied. Manual entries and closed-period overrides may have no source record because they create final exceptional evidence rather than request a change to one visible record. Users can create descriptive source-backed requests from their own visible work sessions, breaks, and Other-work records, and may optionally propose exact start/end timestamp changes; the backend derives proposed exact seconds in `Europe/Warsaw`. Users can cancel their own pending request with a reason. Manager decisions are atomic: only `pending` requests can be rejected or partially corrected, so the first committed manager decision wins. Final corrections preserve final timestamp/seconds values. Manual head-manager entries are stored as final `manual_entry` corrections with a visible request type and history marker. Final correction and Other-work decisions publish one catalogued in-app/email-capable notification to the affected user from the same authorized operation path.

### Settlement period

Default settlement period:

- starts on the 10th;
- ends on the 9th of the next month;
- start day centrally configurable.

Standard corrections apply only to the current period.

Period closes automatically by date.

Current implementation persists settlement settings in `optional_time_tracking.settlement_settings` and concrete periods in `optional_time_tracking.settlement_periods`. The default start day is 10, producing periods from the 10th through the 9th of the following month. The start day is configurable from 1 through 28. Periods are created on demand and due open periods are automatically closed after their `ends_on` date in `Europe/Warsaw`.

Closed-period changes normally require:

- an eligible head manager;
- permission;
- reason;
- password;
- MFA when active or required;
- full audit.

If no eligible head manager exists, an administrator may perform the correction only with:

- a dedicated closed-period override permission;
- active Admin mode;
- fresh high-risk reauthentication;
- a mandatory reason;
- an exact before/after preview;
- enhanced audit.

This fallback is exceptional and does not replace the normal head-manager approval path.

Current implementation stores closed-period override evidence in `optional_time_tracking.closed_period_overrides`. A closed-period override creates a final `closed_period_override` correction only when the caller supplies a reason, before/after preview confirmation, high-risk reauthentication, MFA confirmation, and Admin mode confirmation for admin-scoped overrides. The override preserves original and final exact values and records the actor scope.

The dedicated Admin fallback endpoint is `admin.time-tracking.closed-period-corrections.store` (`POST /admin/time-tracking/closed-period-corrections`). It is protected by authentication, active Admin mode, the route permission `admin.time-tracking.closed-period-corrections.store`, TimeTracking module activation through the `admin.time-tracking.*` permission mapping, and the high-risk administrative operation `closed_period_time_tracking_correction`. The endpoint only accepts the active team as target, rejects use when an active head manager exists for that team, requires confirmed exact before/after values, and writes enhanced security audit records for successful and rejected fallback attempts.

Pending other work does not block closure.

Post-closure approval creates a closed-period correction.

### Reporting

Reports show final values by default and mark:

- corrected;
- manual;
- pending;
- rejected;
- auto-approved;
- under review.

Details show full history.

Use exact seconds internally.

The regular user profile panel is a Core Users surface at route `users.profile` (`/user`) rendered by `resources/js/Pages/User/Panel.vue`. In application mode, the sidebar exposes `Profil użytkownika` / `User profile` under `Pulpity` / `Dashboards`, and exposes `Czas pracy` / `Work time` plus notifications under `Moje sprawy` / `My matters` when the active team grants the corresponding routes. `Czas pracy` is shown only when the active team grants the TimeTracking user-report route and tracking is enabled for the active user-team assignment. The profile panel shows the effective inactivity timeout and, for tracked user-team assignments, the resolved daily regular-break limit. Tracked users also get avatar-menu actions to start a break or start Other work; starting Other work requires a description before the active lock begins. The main application dashboard (`/`) intentionally remains empty during this phase.

Current implementation adds a dedicated manager panel at route `time-tracking.panels.manager` (`/manager`) rendered by `resources/js/Pages/Manager/Panel.vue`. The user dropdown links to this panel when the active team grants the permission. The manager dashboard is intentionally empty during this phase. In manager shell mode, the sidebar exposes `Pulpity` / `Dashboards` with the application dashboard and manager dashboard, then exposes `Ewidencja czasu` / `Work time records` with manager-scoped links for summary, work outside the computer, breaks, corrections, and work sessions. Manager TimeTracking record links are sidebar navigation, not top subnavigation.

The same registry-resolved manager groups and route availability feed desktop and mobile navigation. The mobile shell no longer exposes the legacy manager-report URL as a primary link; it exposes the accepted `/manager/work-time/...` record routes with the same labels, active state, permissions, and ModuleGate-derived availability as desktop.

Current implementation adds the application user report at route `users.work-time` (`/user/work-time`) rendered by `resources/js/Pages/TimeTracking/UserReport.vue`. Its route-backed `section` query selects daily summary, work sessions, breaks, Other work, or corrections, and the backend sends rows only for that section. The view composes shared typed report models, format/status/chart helpers, route tabs, correction dialog, compact filters, and shared `DataTable` instances. Detail sections hide public identifiers by default, render human labels instead of enum internals, and expose `Zglos korekte` / `Request correction` for each visible source record. The break section shows a translated limit badge, excess duration, and danger row treatment when the resolved daily limit is exceeded. Exact seconds are derived by the backend. The old `/time-tracking/report` URL is not kept as a compatibility alias. The route is protected by the `users.work-time` permission and the TimeTracking ModuleGate; creating a correction request additionally requires `users.work-time.corrections.store`.

Every user, manager, and administrator work-time section renders metrics for its own detailed dataset rather than a generic daily summary. The metrics use the complete authorized row set after applied section filters and DataTable search, independent of sorting and pagination. Daily metrics summarize daily rows; work sessions, breaks, Other work, and corrections expose section-relevant counts, durations, states, review signals, and affected-user counts. Interval-based records belong to a bounded report whenever their interval overlaps it, including records started before the range; displayed and summarized seconds are clipped to the overlap so the table and metrics remain numerically consistent.

The legacy standalone route `time-tracking.reports.manager` (`/time-tracking/manager-report`), page, permission, export provider, registered tables, and translation catalog were removed in Phase 28 because they duplicated the canonical manager records workflow and exposed a different composition from Admin. Manager reporting and operations now live exclusively under `/manager/work-time/...`, where a regular manager sees direct reports and a head manager sees the returned subtree through `ManagerHierarchy::scopeFor`. Manager operation tables have manager-specific registered keys and permissions, distinct from their Admin counterparts, and expose private and active-team-shared saved views through the shell-neutral table contract.

TimeTracking audit, realtime publishing, tracked assignment resolution, break policy resolution, break reminder recording, controller access checks, manager operation filters, report user/team labels, closed-period override eligibility, operation details, correction-history actor labels, and bounded audit sections resolve cross-module identifiers through `UserLookup`, `TeamLookup`, `ManagerHierarchy`, and Audit `AuditEventLookup`. Those surfaces must not read Identity, Teams, or Audit persistence directly.

The manager work-time records area is available under `/manager/work-time/summary`, `/manager/work-time/other-work`, `/manager/work-time/breaks`, `/manager/work-time/corrections`, and `/manager/work-time/work-sessions`. It reuses the accepted Admin work-time records component in manager shell mode, but does not render the Admin top subnavigation. The backend builds team and user filters from the manager's TimeTracking-enabled teams and the Teams `ManagerHierarchy::scopeFor` result, so a manager who manages multiple teams can select among only those teams where they have the relevant manager work-time permission and TimeTracking module access. Work-time navigation preserves the applied query-string filters between sections. Each route sends only the rows for its current section in the Inertia payload. Manager work-session visibility is intentionally enabled so managers can inspect sessions needed for corrections; this visibility remains limited to direct reports or subtree users according to manager scope.

Manager record details and operations use dedicated `/manager/work-time/...` route names and permissions. Managers may open details, terminate active subordinate sessions, force-close active subordinate breaks or Other-work records, convert excess break time, decide Other-work records, and decide correction requests only when the record belongs to their manager scope and the specific manager route permission is granted. Correction decisions are not time-limited for managers or administrators. Team Other-work categories can be managed from the manager panel when the manager has the dedicated category permissions and manager scope for the team.

The Admin work-time records area is shown to operators as `Ewidencja czasu` / `Work time records`, not as the internal module name. The Admin sidebar exposes it as one entry under `Obszary operacyjne` / `Operational areas`; the concrete TimeTracking views live in the top subnavigation:

- `admin.work-time.summary.index` (`/admin/work-time/summary`) as the Admin `Podsumowanie` / `Summary` view;
- `admin.work-time.other-work.index` (`/admin/work-time/other-work`);
- `admin.work-time.breaks.index` (`/admin/work-time/breaks`);
- `admin.work-time.corrections.index` (`/admin/work-time/corrections`);
- `admin.work-time.work-sessions.index` (`/admin/work-time/work-sessions`).

All routes render `resources/js/Pages/TimeTracking/AdminOperations.vue` in Admin shell mode with Admin breadcrumbs, dedicated route permissions, TimeTracking ModuleGate mapping through the `admin.work-time.*` permission prefix, and Admin DataTable export routing. Admin data is not loaded as one company-wide default. The operator first selects a tracked team; only then does the selected view load records for all TimeTracking-enabled user-team assignments in that team, without manager-hierarchy scope limits. User and Other-work category filter options are loaded as catalogs for all tracked teams and then narrowed immediately in the frontend for the selected team; the option lists are not derived from the currently displayed table rows. Detail views and operational actions authorize against the same Admin-visible tracked-team catalog used by the filters. Current Atlas foundation/Admin/user/manager routes record only the `System` context, so the subsection has no context selector until a business module introduces an explicit user-facing work context. The team selector is the Admin counterpart of the already enforced manager direct-report/subtree scope.

The Admin work-time subsection is selected by the `work-time` navigation-registry key and is rendered inline on desktop and as a labeled stacked section in the accessible mobile drawer. Both variants use the same five resolved route entries and active state and preserve the current deterministic query string when switching subsection.

The current split area exposes an Admin summary, Other-work details, break review rows, correction request rows, and technical work-session drill-downs. Selecting a team without a user returns separate rows for every tracked member of that team in every section, including one daily-summary row per user and day; selecting a user narrows every section to that user. The summary and Other-work views reuse the same exact daily-summary and Other-work-detail report concepts as the regular user surface while adding Admin team/user context. Applied team, user, and common range filters remain present when navigating between work-time sections. Admin work-time filters are scoped per subsection: summary keeps range/team/user/comparison controls; Other-work adds category, approval status, decision state, closure reason, and review state; breaks add break status, closure reason, and review state; corrections add correction type, status, and review state; work sessions add open/closed status and closure reason. Query-string filters that do not belong to the active subsection are ignored by the Admin controller before report rows are built. The work-session view shows exact timestamps, exact seconds, closure reason, Laravel session identifier, and counts of related module-context segments, breaks, Other-work records, maintenance impacts, and correction requests. The correction view includes a visible `Dotyczy` / `Applies to` column that identifies whether the request is linked to a work session, break, work outside the computer, or no source record. The break view includes translated break-limit status, excess duration columns, and red danger row treatment so exceeded regular-break limits are visible without relying on color alone. Administrators with the dedicated excess-break conversion permission may convert all or part of an exceeded regular break through a reasoned final correction linked to the break source; Admin/user reports apply the latest corrected final break duration when calculating break totals and excess state. Break and correction views expose pending operational action availability from backend state, and Admin-authorized actions, drill-down details, notifications, and audit-confirmed side effects are wired into these Admin work-time routes rather than a parallel surface.

Admin management of work outside the computer category dictionaries is intentionally separate from the Other-work report index. `/admin/work-time/other-work` remains a report/detail surface for recorded work outside the computer, while `/admin/work-time/other-work/categories` lists team category dictionaries and `/admin/work-time/other-work/categories/create` contains the create/update form. Category deactivation remains a reasoned Admin operation and deactivates the category instead of deleting it. Administrators with the dedicated Other-work decision permission may approve or reject closed Other-work records that still require manager review directly from the Admin Other-work table; the first final decision wins, the record stops requiring manager review, the user is notified, and the reason is recorded in TimeTracking audit.

Admin manual-entry creation is intentionally separate from the correction queue. `/admin/work-time/corrections` stays a list of correction requests and decisions. `/admin/work-time/corrections/manual-entry` renders the dedicated manual-entry form with entry type, tracked team selection, tracked user selection, optional Other-work category, final start timestamp, final end timestamp, and mandatory reason. Supported entry types are work session, break, and work outside the computer. The shared `FormDateTimeInput` owns manual timestamp entry on TimeTracking forms. Operators do not enter final exact seconds in this workflow; the backend derives exact seconds from the validated start/end range in `Europe/Warsaw`, creates the matching final source record, then persists the final `manual_entry` correction linked to that source with audit evidence and user notification. Manual break and Other-work records use an internal zero-duration work-session container for the required persistence relationship; report and work-session list builders ignore that container so it cannot inflate counted work time or appear as a user-facing work session.

The canonical manager and regular-user `Czas pracy` / `Work time` pages are scoped operational self-service views and do not expose the Admin DataTable export endpoint. Admin work-time tables expose CSV, XLSX, PDF, and browser-print actions through Core Exports. Any future module-owned business report catalog is a new feature, not a hidden TimeTracking backend surface.

The regular user report includes a shared Atlas bar chart for the current daily-summary distribution, including counted total, work, regular break, break time requiring review, maintenance/technical break time, total Other work, accepted Other work, and pending Other work. Canonical manager operations use the shared manager/Admin records composition. Charts use the repository-owned `AtlasBarChart` wrapper and receive already authorized report summaries from the backend.

Display hours and minutes by default.

Exports may include readable time and raw seconds or `HH:MM:SS`.

No rounding to 5, 10, or 15 minutes.

Support optional period comparison:

- today vs yesterday;
- week vs previous week;
- settlement period vs previous;
- month vs previous month;
- year vs previous year;
- custom vs previous range of equal length.

The comparison is enabled on the user summary with the report filter `compare=previous`. When enabled for a bounded range, the backend calculates the previous equivalent range, returns current/previous/delta/percentage values for work, break, and accepted Other work seconds, and the UI renders a shared chart plus neutral metric deltas. The `all` range remains non-comparable because it has no bounded previous period.

Do not automatically label increases or decreases as good or bad.

### Impersonation simulation

Official TimeTracking tables, reports, manager feeds, settlements, and notifications exclude impersonation simulation. When impersonation is active, the TimeTracking web middleware does not read official break/Other-work locks and does not create, close, or update official work-session or module-context records for the impersonated user. The Activity Tracker endpoint records only impersonation-scoped simulation metadata through Identity's `ImpersonationSimulationStore`, keyed by impersonation session ID, and returns a simulated active response for UI flow testing. Simulation cache is deleted by the Identity impersonation end path.

### Audit

TimeTracking writes Core Audit events for meaningful application and operational transitions rather than every timestamp update. Current audited actions include official work-session closure reasons, inactivity logout, break start/end/forced/expired/reminder batches, Other work start/end/forced/under-review/expired/reminder batches and Admin approval/rejection decisions, maintenance schedule/start/complete/return, correction requests, cancellation, rejection, manager correction, manual entries, closed-period corrections, and Admin closed-period fallback rejection/success. Admin decisions carry explicit before/after state and a security category; stale duplicate decisions produce rejected evidence. Mandatory decision evidence and the owning state transition share a transaction. Audit events use public user/team identifiers where available and do not copy sensitive Other-work descriptions or end notes into audit metadata; those texts remain in the owning TimeTracking tables under their normal authorization rules.

### Live Status

TimeTracking live manager status uses the shared Notifications realtime foundation rather than a module-local channel. `TimeTrackingLiveStatusPublisher` emits `time_tracking.status.changed` events on topic `time-tracking` for active-team scoped status changes. Payloads include the user public ID, status, occurred-at timestamp, type, optional context/module key, and technical reason where applicable; they intentionally exclude Other-work descriptions and notes. The browser realtime client listens for those events and reloads the current canonical `/manager/work-time/...` payload without toast spam.

### Development demo data

`Database\Seeders\DevelopmentDemoSeeder` delegates to the non-production `TimeTrackingFixtureBuilder` after real TimeTracking tables exist. The builder uses public Identity, Teams, Authorization, activation, session-limit, break-policy, and tracking contracts for external state; its direct writes are restricted to owner-owned deterministic historical review aggregates that normal live commands intentionally cannot backdate. It is idempotent, skips production, and no-ops before TimeTracking migration.

The scenario creates `TT Demo Team North` and `TT Demo Team South`, 2 head managers named `TT Head Manager ...`, 3 regular managers named `TT Manager ...`, and 50 regular users named `TT User ...`, all with the local demo password `password`. It activates TimeTracking for both teams, assigns only the scoped application, notification, user-panel, manager-panel, report, activity, break, and Other-work permissions needed by the demo role, creates manager relationships where head managers see manager subtrees and regular managers see direct reports, enables tracking, and seeds historical and active work sessions, module-context segments, breaks, approved and under-review Other work, and pending correction requests for report review. Each demo team includes at least one source-backed correction for a work session, break, and work outside the computer.

---
# Phase 28 foundation repair target

Current state: TimeTracking uses one route-backed user/manager/Admin work-time model, owner-owned user/team contracts, shared typed UI/status/action/table/formatter foundations, manager/Admin operational parity, and complete atomic audit for corrections, decisions, sessions, breaks, Other work, categories, and maintenance. Stale duplicate decisions record rejection evidence, mandatory audit failure rolls back the owning transition, and the legacy manager-report surface is permanently absent.

Tracked issue IDs: `P28-ARCH-007`, `P28-AUDIT-005`, `P28-TT-001`, `P28-TT-002`, `P28-TT-003`, `P28-TT-004`, `P28-MODAUD-018`.
