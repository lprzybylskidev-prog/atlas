# Phase 41 — Optional Work Schedule and workforce planning

**Status:** `not started`

## Objective

Add an Optional Work Schedule capability that answers:

`When is this User expected to work in this Team, and how much scheduled working time exists in a requested interval?`

The schedule is scoped to `User + Team`.

A User never has one universal global work schedule that overrides Team context.

The same User may have different schedules in different Teams.

This capability provides expected availability/capacity only.

It does not implement HR, leave management, attendance approval, payroll, compensation, or productivity scoring.

## Dependencies

- Phase 36 Team timezone and Business Calendar foundation.
- Phase 37 effective date/instant primitives where useful.
- Existing Users, Teams, Authorization, ModuleGate, Audit, Settings, localization, manager hierarchy, and Calendar foundations.

## Ownership

Create an Optional `WorkSchedule` module/capability.

It owns:

- schedule definitions;
- recurring expected working intervals;
- schedule assignment to User + Team;
- date-specific overrides;
- calculated expected working intervals;
- calculated expected working duration/capacity.

It does not own actual tracked time.

TimeTracking remains the source of actual time.

## Schedule model

A schedule is always evaluated in a Team context.

Persist enough information to preserve wall-clock meaning.

Team canonical IANA timezone from Phase 36 is authoritative for new schedule interpretation unless the schedule explicitly pins the resolved timezone required by the accepted temporal contract.

A Team timezone change must not silently reinterpret already pinned historical/scheduled wall-clock definitions.

## Working intervals

Support:

- different intervals for different weekdays;
- multiple intervals in one day where required;
- non-working weekdays;
- overnight intervals crossing midnight;
- shift-style schedules;
- schedule effective ranges where required;
- date-specific overrides.

Reject invalid overlapping intervals within the same effective User + Team schedule unless an explicit normalization rule safely resolves them.

## Business Calendar

Work Schedule uses the relevant Business Calendar for:

- holidays;
- company non-working days;
- regular working/non-working calendar-day semantics.

Business Calendar does not itself define employee working hours.

Work Schedule does not duplicate Business Calendar persistence.

A User + Team schedule may use the Team default Business Calendar or an explicitly allowed calendar according to the accepted Phase 36 contract.

## Overrides

Support explicit date/interval overrides for exceptional planned working time.

Overrides may:

- add expected working intervals;
- remove expected working intervals;
- replace the normal interval set for the affected date where the model requires.

Maintain clear precedence and Audit history.

Do not model vacation/leave reasons or HR entitlement.

A future HR/Leave capability may integrate through a narrow public availability contract when an actual consumer exists; do not build HR in this phase.

## Public contracts

Expose framework-independent queries for at least:

- expected intervals for User + Team + date/range;
- expected working duration for User + Team + date/range;
- resolved Team timezone/calendar context used for the result.

Results must not expose persistence models.

Consumers include future Work Management, TimeTracking, Reports, and manager views.

## Authorization

Users may see their own schedule where allowed by the product surface.

Manager access follows manager scope and Team ownership.

Schedule administration requires explicit permissions.

Do not infer managerial access from frontend navigation.

## Privacy

Schedules are employee operational data.

Do not expose schedule details through Admin/Health diagnostics beyond safe aggregates unless the viewer is authorized for the schedule itself.

## Audit

Audit:

- schedule definition changes;
- User + Team assignment;
- overrides;
- effective-range changes;
- calendar/timezone-related schedule configuration.

Preserve before/after context without duplicating unrelated personal data.

## Notifications

Do not notify on every schedule calculation.

Meaningful schedule/override changes may produce Notifications only through explicit product rules.

Do not create notification spam.

## Search

Do not index raw employee schedules in global Search by default.

Work Schedule is primarily accessed through owned user/manager surfaces and public queries.

## Files

No Files ownership is required by the baseline Work Schedule capability.

## Managed Processes

Ordinary schedule calculations are synchronous deterministic queries.

Large recalculation/repair/backfill work may use Managed Processes, but do not introduce a second job/process system.

## Reports/exports

Authorized schedule and expected-time reports/exports may use existing Reports/Core Exports.

Exports must preserve Team, timezone, date-range, locale, and authorization context.

## Module activation

Work Schedule is Optional.

When inactive:

- its routes/UI disappear;
- public capability lookup reports unavailable;
- dependent required Optional modules cannot be activated;
- optional consumers use documented reduced mode.

Activation/deactivation follows existing module dependency guards.

## UI

Provide:

- personal schedule view;
- manager Team schedule view;
- schedule administration;
- clear timezone/calendar context;
- override management;
- understandable overnight interval rendering.

Support PL/EN and light/dark.

## Operational visibility

Health checks validate technical availability and invalid configuration counts without exposing employee schedule content.

## Failure behavior

Invalid timezone/calendar/schedule combinations fail explicitly.

Do not silently interpret invalid local times through DST gaps/ambiguities.

DST behavior must be deterministic and tested.

## Explicit non-goals

- HR employee records;
- vacation/leave entitlement;
- sickness/absence domain;
- payroll;
- wages;
- overtime pay;
- productivity scoring;
- activity monitoring;
- actual time tracking;
- automatic task assignment.

## Tasks

### P41-W01 — Module boundary and schedule model

- [ ] Create Optional WorkSchedule module metadata, schema, permissions, public contracts, and docs.
- [ ] Define User + Team ownership and prohibit global User schedules.
- [ ] Define timezone/Business Calendar semantics.

### P41-W02 — Recurring intervals and overnight schedules

- [ ] Implement weekday interval patterns and multiple intervals.
- [ ] Implement overnight crossing-midnight behavior.
- [ ] Add overlap and invalid-local-time validation.

### P41-W03 — Effective assignments and overrides

- [ ] Implement schedule assignment/effective lifecycle.
- [ ] Implement date-specific overrides with explicit precedence.
- [ ] Add Audit and concurrency protection.

### P41-W04 — Expected-time calculations

- [ ] Implement expected-interval and expected-duration queries.
- [ ] Integrate Business Calendar holidays/non-working days.
- [ ] Add timezone/DST boundary coverage.

### P41-W05 — User and manager surfaces

- [ ] Implement personal and authorized manager views.
- [ ] Add administration UI and permissions.
- [ ] Add PL/EN, responsive, light/dark browser coverage.

### P41-W06 — Reports, activation, privacy, docs, closure

- [ ] Add authorized reporting/export integration where useful.
- [ ] Test active/inactive and dependency behavior.
- [ ] Add privacy/operational safeguards.
- [ ] Update canonical Teams, Calendar, TimeTracking-facing, manager, architecture, and testing documentation.

## Completion criteria

- [ ] Schedule ownership is User + Team.
- [ ] Different Team schedules for the same User work correctly.
- [ ] Overnight and DST behavior is deterministic.
- [ ] Business Calendars and Team timezones are reused rather than duplicated.
- [ ] Expected capacity is queryable without implementing HR/payroll/actual time.
- [ ] Authorization, Audit, privacy, activation, PL/EN, and tests are complete.
- [ ] `WORKROAD.md` status is `complete`.
