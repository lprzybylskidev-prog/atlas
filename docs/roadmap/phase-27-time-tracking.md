# Phase 27 — Optional TimeTracking module

**Status:** `in progress`

## Objective

Implement optional TimeTracking only after every known foundation it depends on is complete: sessions, active team, module activation, settings, audit, notifications/realtime, manager hierarchy, Admin mode, reports/exports, privacy, and operational visibility.

## Dependencies

- [Phase 12 — Settings and localization](phase-12-settings-localization.md)
- [Phase 13 — Sessions and active team](phase-13-sessions-active-team.md)
- [Phase 14 — Module availability and activation](phase-14-module-activation.md)
- [Phase 15 — Notifications and realtime foundation](phase-15-notifications-realtime.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Phase 17 — Manager hierarchy](phase-17-manager-hierarchy.md)
- [Phase 18 — Administrative mode and impersonation](phase-18-admin-impersonation.md)
- [Phase 23 — Feature flags](phase-23-feature-flags.md)
- [Phase 24 — Reports, exports, PDF, charts, and print](phase-24-reports-exports-print.md)
- [Phase 26 — Security, privacy, deletion, and anonymization](phase-26-security-privacy.md)
- [TimeTracking module documentation](../modules/time-tracking.md)

## Implementation contract

- `TimeTracking` is optional and self-contained.
- It may be enabled for one user-team assignment and disabled for another.
- TimeTracking must not depend on a future HR, payroll, leave, or personnel module. Any such integration requires a separate explicit roadmap decision and public contracts.
- Work starts automatically after login to a tracked team and ends at logout; there is no normal separate end-work button.
- Team switching:
  - tracked -> untracked closes the tracked segment and starts none;
  - untracked -> tracked starts work;
  - tracked -> tracked closes old team work and starts new team work;
  - switch is blocked during break and other work.
- Track active application module as time segments with start/end, user, and team.
- A route/module change closes the previous segment and opens a new one.
- With multiple tabs, the tab with last focus or real activity is active. Background tabs never create parallel module time.
- General pages use `System` or `Workspace` context, so there are no unexplained work-time gaps.
- Store exact seconds and raw timestamps. Never round each segment. Aggregate exact seconds first and format final totals afterward.
- Report default presentation is hours and minutes; details may show seconds; exports may include readable values and raw seconds/`HH:MM:SS`.
- No rounding to 5, 10, or 15 minutes.

### Breaks

- Users may take any number of breaks per calendar day.
- Daily break limit resolves in order: user-team override -> team override -> global default.
- Limit is separate per user-team.
- Excess break time is not counted as work.
- Break view shows remaining time and then `exceeded by`.
- Break locks the whole application. Every route redirects to the break screen and backend independently blocks business actions.
- Ending break requires password and MFA when active or required.
- Team switching and normal logout are blocked during break.
- Forced termination, account deactivation, Admin/manager invalidation, maintenance, or failure may close it technically; audit must distinguish this from normal user return.
- Break may cross midnight as one logical record but reporting splits exact time by `Europe/Warsaw` calendar day and consumes each day's appropriate limit.
- Maximum single-break duration resolves user-team -> team -> global, with a suggested default of 4 hours.
- Before maximum duration, show configurable reminders and a countdown, including an example warning 15 minutes before.
- At maximum duration, technically close break, end the session, notify user and manager, and mark the record for review/correction.

### Other work

- `Other work` represents work outside the computer.
- Starting it requires description and, where configured, a category. End note is optional.
- Categories may be global or team-specific and have stable key, PL/EN label, optional description, ordering, and optional mandatory-comment rule.
- Used categories are deactivated, never physically deleted.
- Global categories are managed by Admin with permission. Team categories are managed by head manager or Admin. Ordinary managers only use them.
- Descriptions and end notes are visible to the user, authorized managers, and Admin with permission; they are hidden from the compact live feed and ordinary email, hidden by default in aggregate reports, and require separate permissions for text search and export.
- Other work locks normal UI but background jobs/uploads continue.
- Mouse/keyboard inactivity does not end Other work because work is outside the computer.
- Normal logout is blocked until the user ends the record.
- Forced close preserves a pending record and marks the technical reason.
- Maximum duration resolves user-team -> team -> global, with suggested default 12 hours.
- Give configurable reminders, for example after 2 hours and 30 minutes before maximum, plus countdown.
- At maximum, technically close, notify, and require manager verification.
- Default approval is manual.
- A category may explicitly enable auto-approval globally or per team.
- Before start, user sees whether manager approval will be required.
- Auto-approval is audited.
- Any emergency, technical, maintenance, or maximum-time close always requires manual review.
- Authorized manager may challenge auto-approved work, moving it to `under_review` with reason and notification. It stops counting as accepted until final decision.

### Maintenance

- Maintenance is global for the whole application, never module-specific.
- It may be scheduled with advance warning or started immediately as emergency.
- Only tracked users with an active work session at maintenance start receive maintenance time.
- Users who logged out before start receive none.
- Maintenance interrupts break and technically closes Other work; Other work remains subject to normal approval.
- Maintenance time always counts as work.
- After maintenance, allow a maximum 10-minute re-login grace:
  - if the user returns in time, count the gap from maintenance end to login;
  - otherwise, count no post-maintenance gap and end work at maintenance end.
- Apply the grace only to users who received maintenance time.

### Inactivity and offline

- Shared frontend Activity Tracker monitors real mouse, click, keyboard, scroll, touch, and similar events with throttling.
- Backend is authoritative.
- After the individual inactivity threshold, show a modal with a 20-second countdown.
- Any real activity dismisses/reset the warning without requiring a button.
- If there is no activity, counted work ends at the warning start and the session logs out.
- Browser close/pagehide/visibility are hints only; backend closes after the threshold.
- Multiple tabs synchronize activity.
- Only one real working device/session may count time. A second-device login warns and asks whether to terminate the existing session before the new work session starts.
- Temporary internet loss does not immediately end work.
- Show offline state and synchronize on reconnect.
- If offline duration exceeds individual inactivity timeout, end normally rather than count indefinitely.
- On reconnect, tell the user whether the session is active or ended.
- Incorrect accounting is corrected through the normal correction request flow.

### Managers and corrections

- Manager panel has one primary report view, not separate pages for each range.
- Time range filter options: today, week, settlement period, month, year, all, custom.
- Week is Monday-Sunday in `Europe/Warsaw`.
- Month/year are calendar periods.
- `all` uses server pagination and warns about full history. Very large PDF/print may require narrowing; CSV/XLSX may queue.
- `custom` supports date-only full days or exact date/time, plus fixed or relative saved ranges.
- Manager filters include user, current live status, historical entry type, one/many modules, `System`, detailed context, and users with no module activity.
- Current live status and historical record type are separate filters.
- Live status uses WebSockets and shows work, break, Other work, maintenance, offline, no session, and current module.
- Above the table show compact counts and pending manager decisions.
- Add a limited live feed for work start, break, Other work, return, team switch, session end, inactivity logout, and maintenance start/end.
- Feed includes only visible subordinates, hides sensitive Other-work text, supports authorized detail links, and never replaces audit.
- Reports show final values and mark corrected/manual/pending/rejected/auto-approved/under-review records.
- Detail shows original, proposed, final, reasons, actors, and history.
- Employee may submit:
  - descriptive correction request;
  - exact proposed changes.
- Employee may cancel own `pending` correction or Other-work request with reason. Do not delete; set `cancelled`, notify manager if queued, and audit.
- Manager may approve, reject, or partially correct. Rejection/correction requires reason.
- First authorized manager decision wins.
- Head manager with separate permission may create a completely new manual entry for exceptional missing time; require reason, password, MFA where applicable, audit, and a visible manual marker.
- Decision deadline is 5 calendar days, not business days.
- First reminder after 3 days, overdue at day 5, then grouped reminders no more often than daily.
- No automatic rejection.
- Head manager may take over overdue items. If no head manager exists, Admin with permission may decide.
- Direct manager acts on direct reports. Head manager acts on their subtree.
- Managers may correct start/end, breaks, Other work, and maintenance only through explicit use cases with original/final values, reason, notification, and audit.
- Maintenance correction requires a special permission.
- Manager may terminate a subordinate active session with permission, reason, notification, and audit.
- User correction request remains the normal response to offline/technical inaccuracies.

### Periods, ranges, comparison, exports

- Default settlement period begins on day 10 and ends on day 9 of next month; start day is centrally configurable.
- Standard corrections apply to current period only.
- Period closes automatically by date; managers do not manually close it.
- Closed-period edits require head manager, permission, reason, password, MFA where active/required, complete audit, and original/new values.
- Pending Other work does not block closure and remains uncounted/overdue.
- After closure only head manager may decide it; approval creates a closed-period correction.
- Optional comparison mode supports today vs yesterday, week vs previous week, settlement period vs previous, month vs previous month, year vs previous year, and custom vs immediately preceding equal-length range.
- Comparison shows current, previous, absolute difference, and percentage difference in summary plus a detailed user/metric table.
- Color indicates direction only, never automatically good/bad.
- Compare work, breaks, excess break, accepted Other work, maintenance, and module activity.
- Reports reuse shared TanStack Table, saved views, CSV/XLSX/PDF/print, report headers, permissions, queued exports, and Atlas chart wrappers.
- PDF, XLSX, print include team, filters, range, generation time, generating user, timezone, totals, company identity; PDF/print include pages.
- Timezone for all daily/weekly/monthly/yearly/settlement logic is `Europe/Warsaw`. Daylight-saving changes must not create artificial work.
- Timestamp transport remains ISO with timezone.
- TimeTracking is excluded from official reports during impersonation; UI simulation is isolated.
- TimeTracking is a trustworthy operational data foundation, not an automatic employee-rating engine.
- Atlas does not calculate a default productivity score, performance grade, or good/bad employee classification.
- Managers, analysts, or a separately designed future analytics module may interpret the collected data.
- Collect data suitable for later analysis, including work time, active time, inactivity, breaks, other-work intervals, module context, team context, role context, process/workflow context, and explicitly registered business events or metrics supplied by modules.
- Do not infer productivity solely from mouse movement, click counts, keyboard activity, or time in front of the screen.
- Future metric definitions must be explicit, versioned, and attributable to their owning module or analytics policy.
- Preserve snapshots of relevant team, role, process, and metric-definition context so historical analysis remains reproducible.
- Reports may be recalculated against a selected metric/rule version when the required source data exists.
- Every analytical result must be traceable to source events and the applied rule version.
- Do not introduce one global performance score without a separate explicit architectural and business decision.
- Do not make automated HR, payroll, bonus, sanction, or disciplinary decisions.
- Any use affecting employment decisions requires a separate project with legal, privacy, and business review.
- Work, break, and other-work intervals may cross midnight as one logical interval; reporting, daily limits, and settlement split exact duration by the `Europe/Warsaw` calendar day.
- Official timestamps and state are backend-authoritative.
- Client offline elapsed time uses a monotonic clock such as `performance.now()`, sequence/lease identifiers, and a server anchor; never trust the user's wall clock.
- Reconciliation rejects duplicate, reordered, excessive-gap, expired-session, parallel-work, or clock-anomaly events.
- Closed-period corrections normally require an eligible head manager. If none exists or an emergency override is required, an administrator with a dedicated permission may act only in Admin mode with fresh high-risk reauthentication, mandatory reason, exact before/after preview, and enhanced audit.

## Tasks

- [ ] Implement logical intervals crossing midnight with exact calendar-day allocation for reports, limits, and settlements.
- [ ] Implement monotonic client elapsed-time capture and backend-authoritative offline reconciliation.
- [ ] Reject duplicate, reordered, excessive-gap, expired-session, parallel, and clock-anomaly offline events.
- [ ] Implement the dedicated Admin fallback for closed-period correction when no eligible head manager exists.
- [ ] Require Admin mode, fresh high-risk reauthentication, dedicated permission, reason, preview, and enhanced audit for that fallback.
- [ ] Define TimeTracking as an operational-data source rather than an employee-rating engine.
- [ ] Ensure no default productivity/performance score is implemented.
- [ ] Define extensible module-owned business-event and metric input contracts.
- [ ] Snapshot team, role, process, and metric-definition context needed for historical analysis.
- [ ] Version future metric definitions and calculation rules.
- [ ] Preserve source-event traceability for every derived analytical result.
- [ ] Support recalculation against a selected metric/rule version where source data permits.
- [ ] Document that mouse, click, keyboard, and screen-time data alone cannot determine productivity.
- [ ] Document that HR, payroll, bonus, sanction, and disciplinary automation are outside scope.
- [ ] Create `TimeTracking` module manifest and contracts.
- [ ] Ensure it can be disabled globally and per team.
- [ ] Register TimeTracking deactivation guards that block disabling while active work, break, other-work, maintenance, pending corrections, or unsafe reporting jobs exist.
- [ ] Feed module activation into TimeTracking module-context segments and enforce ModuleGate for TimeTracking routes, jobs, reports, and live status channels.
- [ ] Ensure TimeTracking remains self-contained and has no HR/payroll dependency.
- [ ] Define TimeTracking public API.
- [ ] Implement user-team tracking enablement.
- [ ] Implement automatic work start after login.
- [ ] Implement work end on logout.
- [ ] Implement team-switch segment transitions.
- [ ] Block team switching during break and other work.
- [ ] Implement one active real working device/session.
- [ ] Implement multi-tab synchronization.
- [ ] Implement module-context segments.
- [ ] Implement `System`/`Workspace` context.
- [ ] Store exact seconds without per-segment rounding.
- [ ] Implement global/team/user-team break limits.
- [ ] Implement break lock screen.
- [ ] Require password and MFA to end break when applicable.
- [ ] Block normal logout during break.
- [ ] Implement forced break closure.
- [ ] Split cross-midnight breaks in reporting.
- [ ] Implement maximum single-break duration.
- [ ] Add break reminders and countdown.
- [ ] Implement other-work categories.
- [ ] Implement team-specific categories.
- [ ] Implement required comments per category.
- [ ] Implement other-work locked screen.
- [ ] Disable inactivity logout during other work.
- [ ] Implement maximum other-work duration.
- [ ] Add reminders and countdown.
- [ ] Implement default manager approval.
- [ ] Implement category-based auto-approval.
- [ ] Implement `under_review`.
- [ ] Require manual review after technical or timeout closure.
- [ ] Implement global maintenance.
- [ ] Implement scheduled and emergency maintenance.
- [ ] Interrupt break and close other work at maintenance start.
- [ ] Implement 10-minute post-maintenance re-login rule.
- [ ] Implement frontend Activity Tracker.
- [ ] Implement backend authoritative inactivity.
- [ ] Implement 20-second inactivity warning.
- [ ] End counted work at warning start.
- [ ] Implement browser-close fallback.
- [ ] Implement temporary offline handling.
- [ ] Implement correction requests.
- [ ] Implement exact-change proposals.
- [ ] Implement cancellation of pending requests.
- [ ] Implement manager approval, rejection, and correction.
- [ ] Implement first-decision-wins.
- [ ] Implement manual head-manager entries.
- [ ] Implement 5-calendar-day decision deadline.
- [ ] Implement reminders after 3 days and at overdue.
- [ ] Implement head-manager/admin overdue takeover.
- [ ] Implement configurable settlement-period start day.
- [ ] Implement automatic period closure.
- [ ] Implement closed-period high-risk correction flow.
- [ ] Build user report.
- [ ] Build manager main report view.
- [ ] Add range filters: today, week, settlement period, month, year, all, custom.
- [ ] Add live manager status through WebSockets.
- [ ] Add compact team summary.
- [ ] Add live status-change feed.
- [ ] Add current-status and historical-type filters.
- [ ] Add module filters.
- [ ] Add saved views through shared table module.
- [ ] Add CSV, XLSX, PDF, and print through shared reporting.
- [ ] Add optional period comparison.
- [ ] Add report charts through shared Atlas chart wrappers.
- [ ] Ensure impersonation time is simulated and excluded from official records.
- [ ] Add development-only demo seeders for example TimeTracking scenarios after real TimeTracking tables exist.
- [ ] Add complete audit.
- [ ] Add complete tests.
- [ ] Add module documentation.
- [ ] Commit TimeTracking module.

## Completion criteria

- [ ] TimeTracking is optional, module-gated, team-aware, audited, reportable, and independent of future HR/payroll modules.
- [ ] Work, break, Other work, maintenance, inactivity, offline reconciliation, corrections, manager decisions, and closed-period flows are complete.
- [ ] Reports reuse the shared reporting/export foundation and never treat impersonation simulation as official time.
- [ ] Complete tests and module documentation are current.
