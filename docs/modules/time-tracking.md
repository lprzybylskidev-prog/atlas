# TimeTracking module

Canonical complete behavior of the optional TimeTracking module. Read this only for work touching time registration, breaks, other work, inactivity, corrections, settlement, reports, or impersonation simulation.

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

### User-team activation

Time tracking is enabled per user-team assignment.

The same user may be tracked in one team and untracked in another.

### Work start and end

- work starts automatically after login into a tracked team;
- logout ends work;
- there is no separate normal end-work button;
- tracked-to-untracked team switch ends the old segment;
- untracked-to-tracked starts a new segment;
- tracked-to-tracked ends the old and starts a new one;
- switching is blocked during break or other work.

### Module activity

Track active module as time segments.

A module change:

- closes the previous segment;
- opens a new segment.

With multiple tabs:

- the tab with latest focus or real user activity is active;
- background tabs do not create parallel activity;
- synchronize one active module across tabs.

General pages count as work under a system context such as `System` or `Workspace`.

Store raw timestamps to the second.

Work, breaks, and other-work intervals may cross midnight as one logical interval. Persist the real start/end timestamps; split durations by the `Europe/Warsaw` calendar day only for reporting, limits, settlements, and exports.

Do not round individual segments.

Aggregate first, format later.

### Offline and client-clock integrity

The backend is authoritative for official timestamps and state transitions.

Client-side offline/activity duration uses a monotonic clock such as `performance.now()` for elapsed duration and never trusts the user's wall clock for official time.

Offline events include a bounded monotonic duration, sequence identifier, tab/device lease identifier, and server-known anchor. The backend validates ordering, maximum gaps, duplication, session validity, and clock anomalies before accepting or rejecting reconciliation.

Offline reconciliation cannot create parallel work, bypass inactivity rules, extend an expired session, or overwrite a newer server state.

### Breaks

Users may take any number of breaks.

Daily break limit:

- global default;
- team override;
- user-team override;
- resolution order: user-team, team, global.

Break limit is calculated per user-team and calendar day in `Europe/Warsaw`.

Excess break time is not counted as work.

Break UI shows remaining time and then exceeded time.

During break:

- whole application is locked;
- any URL redirects to break view;
- backend enforces the lock;
- ending break requires password and MFA when active or required;
- team switching is blocked;
- normal logout is blocked.

Forced session termination closes the break technically and audits the reason.

Breaks may cross midnight and are split in reporting by calendar day without ending the logical break.

Maximum single break duration:

- global;
- team;
- user-team;
- default approximately 4 hours;
- technical auto-close after limit;
- session ends;
- item requires manager review;
- user and manager are notified.

Warn before auto-close and show a countdown.

### Other work

Users may start `Other work` at any time.

Require:

- description;
- category when configured;
- optional end note.

Categories:

- global or team-specific;
- typed stable key;
- PL/EN labels;
- optional description;
- optional required comment;
- deactivate instead of delete;
- audited.

Other work locks the normal application UI but lets background async processes continue.

No inactivity logout applies during other work.

Maximum duration:

- global;
- team;
- user-team;
- default approximately 12 hours;
- auto-close at limit;
- requires manager review;
- notifications sent.

Reminders and countdowns are configurable.

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
- sends planned warnings;
- is audited.

After maintenance:

- allow up to 10 minutes for re-login;
- if user returns in time, count the gap;
- otherwise end at maintenance completion.

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
- 20-second countdown;
- any real activity dismisses it;
- no button is required;
- if no activity occurs, counted work ends at warning start and user is logged out.

If the browser closes, backend ends work after inactivity threshold.

Multiple tabs share activity state.

Only one real active working device/session is allowed.

A second-device login warns and asks whether to terminate the previous work session.

### Offline behavior

Temporary connection loss does not end work immediately.

- show offline status;
- synchronize after reconnect;
- if offline exceeds the inactivity threshold, end work normally;
- do not count indefinitely;
- inform user after reconnect whether session remains active;
- incorrect accounting is handled through a correction request.

### Managers

Manager hierarchy is team-scoped and shared with the manager system.

Direct managers see direct reports.

Head managers see their subtree.

Manager panel includes one main report view with time-range filter:

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

Provide compact team summary above the main table and a limited live status-change feed.

### Corrections and approvals

Users may:

- request descriptive correction;
- propose exact changes;
- cancel their own pending request with reason.

Managers may:

- approve;
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

Standard decision deadline:

- 5 calendar days;
- first reminder after 3 days;
- overdue on day 5;
- grouped reminders after overdue;
- no automatic rejection.

Head manager may take over overdue decisions.

If no head manager exists, administrator with permission may decide.

### Settlement period

Default settlement period:

- starts on the 10th;
- ends on the 9th of the next month;
- start day centrally configurable.

Standard corrections apply only to the current period.

Period closes automatically by date.

Closed-period changes normally require:

- an eligible head manager;
- permission;
- reason;
- password;
- MFA when active or required;
- full audit.

If no eligible head manager exists, or an explicitly approved emergency override is required, an administrator may perform the correction only with:

- a dedicated closed-period override permission;
- active Admin mode;
- fresh high-risk reauthentication;
- a mandatory reason;
- an exact before/after preview;
- enhanced audit.

This fallback is exceptional and does not replace the normal head-manager approval path.

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

Do not automatically label increases or decreases as good or bad.

---
