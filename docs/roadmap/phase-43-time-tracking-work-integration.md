# Phase 43 — Optional TimeTracking work integration and actual-time analysis

**Status:** `not started`

## Objective

Evolve the already completed Optional TimeTracking module through a new future phase.

Do not rewrite Phase 27.

Integrate TimeTracking with:

- Phase 41 Work Schedule;
- Phase 42 Work Management;
- typed business-resource context.

The purpose is to understand actual work time and neutral expected-versus-actual duration, not to impose target duration or productivity scores.

## Dependencies

- Completed historical Phase 27 TimeTracking.
- Phase 37 typed references.
- Phase 41 Work Schedule for expected work time where active.
- Phase 42 Work Management for Task context where active.
- Existing Audit, Reports, Core Exports, Authorization, manager hierarchy, privacy, localization, and ModuleGate foundations.

Work Schedule and Work Management are Optional dependencies of TimeTracking integration.

TimeTracking must retain a safe reduced mode when either is inactive.

## Expected versus actual

Where Work Schedule is active:

- expected work time comes from Work Schedule;
- actual work time comes from accepted TimeTracking segments;
- expose a neutral signed delta.

Example semantics:

`delta = actual accepted work time - expected scheduled work time`

Positive and negative values are neutral duration facts.

Do not label them as performance, quality, compliance, or compensation outcomes.

## Automatic acceptance

Normal User-recorded valid work time becomes accepted actual time automatically according to existing TimeTracking rules.

Do not require a manager to approve every User/day/segment.

Manager review is exception-based.

## Manager corrections

Authorized managers may correct or reject time only through explicit existing/evolved correction use cases.

Corrections require:

- actor;
- reason/change reason;
- before/after;
- timestamp;
- Audit;
- authorization;
- concurrency safety.

Do not silently rewrite historical time.

## Task context

Where Work Management is active, a TimeTracking work segment may reference a Task.

Use typed public references/contracts.

Do not read Work Management tables.

A Task may have multiple interrupted time segments.

Actual Task duration is the sum of accepted attributable segments.

Example:

- Task A: 09:00–09:40;
- unrelated work: 09:40–10:10;
- Task A: 10:10–11:15.

Actual Task A duration is 1h45m.

Do not merge the source history into one fake continuous segment.

## Other work attribution

Existing `Other work` may be attributed/re-attributed to a Task through an authorized correction when appropriate.

Preserve before/after history and Audit.

Do not destroy the original segment history.

## Business context

Time segments may carry a safe typed business-resource context where required.

Do not persist foreign Eloquent/table identifiers.

Do not make TimeTracking a universal business event store.

## No estimates

Do not add estimated Task duration as a required TimeTracking concept.

Do not provide estimated-versus-actual worker scoring.

Historical actual duration may later inform human or future machine work-distribution decisions, but this phase does not build automatic assignment.

## Reports

Provide authorized reports for:

- expected scheduled time;
- actual accepted work time;
- neutral delta;
- actual Task duration;
- work by Task/resource context;
- Team/User trends within authorized scope.

Reports must state data scope and period clearly.

Do not create productivity scores.

## Manager views

Managers may inspect:

- actual time;
- expected schedule time;
- deltas;
- actual Task duration;
- correction history;
- aggregate Team context.

Manager scope follows existing hierarchy.

## Search

Do not globally Search-index sensitive raw time segments unless a concrete accepted Search surface requires it.

## Notifications

Do not notify managers to approve ordinary time.

Notifications may be used for exceptional correction/rejection workflows only when meaningful.

## Privacy

TimeTracking remains sensitive employee operational data.

Do not expose raw time data to unrelated modules merely because Task context exists.

## HR/Leave boundary

Do not put vacation, sickness, leave entitlement, payroll, or absence management into TimeTracking.

Work Schedule remains the expected-time source.

A future HR/Leave capability may later affect expected availability through a dedicated contract; do not implement HR now.

## Explicit non-goals

- rewriting Phase 27;
- productivity score;
- performance ranking;
- mouse/keyboard activity as productivity;
- Task time estimates;
- expected completion duration;
- payroll;
- wage/overtime pay calculation;
- HR/leave management;
- automatic Task assignment.

## Tasks

### P43-W01 — Integration contract audit

- [ ] Re-read completed TimeTracking implementation/contracts without modifying Phase 27 history.
- [ ] Define optional Work Schedule and Work Management public integration points.
- [ ] Add module metadata/reduced-mode rules.

### P43-W02 — Task attribution

- [ ] Add typed Task context to eligible work segments.
- [ ] Preserve interrupted segment history.
- [ ] Implement actual Task-duration aggregation.
- [ ] Add Other-work attribution corrections.

### P43-W03 — Expected versus actual

- [ ] Query expected User + Team time from Work Schedule.
- [ ] Calculate neutral signed deltas.
- [ ] Add timezone/calendar/date-range tests.

### P43-W04 — Automatic acceptance and exception corrections

- [ ] Preserve automatic normal acceptance.
- [ ] Ensure no daily manager approval requirement exists.
- [ ] Harden audited manager corrections with reason, before/after, concurrency, and authorization.

### P43-W05 — Reports and manager analysis

- [ ] Add expected/actual/delta/actual-Task-duration reports.
- [ ] Add authorized manager views.
- [ ] Prohibit productivity scoring and estimated-duration comparisons.

### P43-W06 — Privacy, activation, browser tests, docs

- [ ] Test TimeTracking with Work Schedule on/off and Work Management on/off.
- [ ] Add privacy, reports/exports, localization, browser, Audit, and regression coverage.
- [ ] Update current TimeTracking documentation without rewriting historical Phase 27.

## Completion criteria

- [ ] Historical Phase 27 remains unchanged.
- [ ] TimeTracking integrates through public contracts only.
- [ ] Expected time comes from User + Team Work Schedule.
- [ ] Actual time remains TimeTracking-owned.
- [ ] Normal time does not require daily manager approval.
- [ ] Manager corrections are exception-based and fully audited.
- [ ] Actual Task duration works across interrupted segments.
- [ ] No Task estimate/productivity score/payroll/HR behavior exists.
- [ ] `WORKROAD.md` status is `complete`.
